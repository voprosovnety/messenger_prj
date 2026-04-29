<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class TypingController
{
    #[Route('/api/chats/{chatId}/typing', name: 'chat_typing', methods: ['POST'])]
    public function __invoke(
        string $chatId,
        EntityManagerInterface $em,
        UserInterface $me,
        HubInterface $hub,
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

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());

        $payload = json_encode([
            'type' => 'user.typing',
            'data' => [
                'userId' => (string) $me->getId(),
                'username' => $me->getUsername(),
                'chatId' => (string) $chat->getId(),
            ],
        ], JSON_UNESCAPED_SLASHES);

        $hub->publish(new Update($topic, $payload, true));

        return new JsonResponse(['ok' => true]);
    }
}
