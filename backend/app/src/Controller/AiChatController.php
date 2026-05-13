<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

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

    #[Route('/api/ai/chat', name: 'ai_chat', methods: ['POST'])]
    public function __invoke(Request $request, UserInterface $me): JsonResponse
    {
        $apiKey = (string) ($_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY'));
        if ($apiKey === '') {
            return new JsonResponse(['error' => 'AI assistant not configured'], 503);
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

        $payload = json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'system'     => self::SYSTEM_PROMPT,
            'messages'   => $clean,
        ], JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ]),
                'content'       => $payload,
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
        ]);

        $raw = @file_get_contents('https://api.anthropic.com/v1/messages', false, $context);
        if ($raw === false) {
            return new JsonResponse(['error' => 'Could not reach AI service'], 502);
        }

        $resp = json_decode($raw, true);
        $reply = $resp['content'][0]['text'] ?? null;
        if ($reply === null) {
            $errMsg = $resp['error']['message'] ?? 'Empty response from AI';
            return new JsonResponse(['error' => $errMsg], 502);
        }

        return new JsonResponse(['reply' => $reply]);
    }
}
