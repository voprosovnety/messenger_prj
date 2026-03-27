<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class MercureSubscribeTokenController
{
    #[Route('/api/chats/{chatId}/mercure-token', name: 'chat_mercure_token', methods: ['GET'])]
    public function __invoke(
        string $chatId,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }

        $membership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        // topic, который клиент будет слушать
        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());

        // subscriber JWT для Mercure (HS256), с ограничением на subscribe
        $secret = $_ENV['MERCURE_JWT_SECRET'] ?? getenv('MERCURE_JWT_SECRET');
        if (!$secret) {
            return new JsonResponse(['error' => 'mercure secret not configured'], 500);
        }

        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + 3600, // 1 час
            'mercure' => [
                'subscribe' => [$topic],
            ],
        ];

        $token = $this->jwtHs256($payload, $secret);

        return new JsonResponse([
            'topic' => $topic,
            'token' => $token,
            'expires_in' => 3600,
        ]);
    }

    private function jwtHs256(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $segments = [];
        $segments[] = $this->b64url(json_encode($header, JSON_UNESCAPED_SLASHES));
        $segments[] = $this->b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = $segments[0] . '.' . $segments[1];
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->b64url($signature);

        return implode('.', $segments);
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
