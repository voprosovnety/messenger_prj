<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiChatController
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a helpful AI assistant built into RealtimeChat — a modern real-time messaging app.
You can answer questions about the app's features and have general friendly conversations.

RealtimeChat features:
- Real-time messaging via SSE (Mercure hub)
- Direct messages and group chats with owner/member roles
- File attachments: images, video, audio, documents up to 50 MB
- Voice message recording (mic button in composer) and custom audio player
- Reply to specific messages with quote preview
- Message editing and soft deletion (shows "Message deleted" placeholder)
- Read ✓✓ / Delivered ✓ receipts
- Typing indicators
- User profiles with username, email, avatar URL
- Online status and "last seen" timestamps
- JWT authentication with auto-refresh via refresh tokens

Answer in the same language the user writes in. Be concise and friendly.
Reply in plain text only — no markdown, no asterisks, no bullet symbols, no headers.
PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RateLimiterFactory $aiLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/ai/chat', name: 'ai_chat', methods: ['POST'])]
    public function __invoke(Request $request, UserInterface $me): JsonResponse
    {
        $apiKey = (string) ($_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY'));
        if ($apiKey === '') {
            return new JsonResponse(['error' => 'AI assistant not configured'], 503);
        }

        // Rate limit: 20 requests per minute per user.
        $limiter = $this->aiLimiter->create((string) $me->getUserIdentifier());
        $limit   = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['error' => 'Rate limit exceeded'],
                429,
                ['Retry-After' => (string) max(0, $limit->getRetryAfter()->getTimestamp() - time())]
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        /** @var array<array{role: string, content: string}> $messages */
        $messages = $data['messages'] ?? [];
        if (empty($messages)) {
            return new JsonResponse(['error' => 'messages is required'], 400);
        }

        // Keep last 20 turns to stay within token budget
        $messages = array_slice($messages, -20);

        // Sanitise roles — only 'user' and 'assistant' are valid
        $clean = [];
        foreach ($messages as $m) {
            $role    = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim($m['content'] ?? '');
            if ($content !== '') {
                $clean[] = ['role' => $role, 'content' => $content];
            }
        }

        if (empty($clean)) {
            return new JsonResponse(['error' => 'messages is required'], 400);
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 1024,
                    'system'     => self::SYSTEM_PROMPT,
                    'messages'   => $clean,
                ],
                'timeout' => 30,
            ]);

            $resp  = $response->toArray(false);
            $reply = $resp['content'][0]['text'] ?? null;

            if ($reply === null) {
                // Log upstream detail server-side; return generic message to client.
                $upstreamError = $resp['error']['message'] ?? 'Empty response from AI';
                $this->logger->error('AI upstream error', [
                    'upstream_message' => $upstreamError,
                    'status_code'      => $response->getStatusCode(),
                ]);

                return new JsonResponse(['error' => 'AI service error'], 502);
            }
        } catch (\Throwable $e) {
            $this->logger->error('AI request failed', ['exception' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Could not reach AI service'], 502);
        }

        return new JsonResponse(['reply' => $reply]);
    }
}
