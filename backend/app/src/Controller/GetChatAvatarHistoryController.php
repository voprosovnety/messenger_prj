<?php

namespace App\Controller;

use App\Entity\AvatarHistory;
use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class GetChatAvatarHistoryController
{
    #[Route('/api/chats/{chatId}/avatars', name: 'chat_avatar_history', methods: ['GET'])]
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

        $membership = $em->getRepository(ChatMember::class)->findOneBy(['chat' => $chat, 'member' => $me]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $rows = $em->getRepository(AvatarHistory::class)->findBy(
            ['chat' => $chat],
            ['createdAt' => 'DESC'],
            20,
        );

        return new JsonResponse([
            'items' => array_map(
                static fn(AvatarHistory $h) => ['url' => $h->getAvatarUrl(), 'created_at' => $h->getCreatedAt()->format(DATE_ATOM)],
                $rows,
            ),
        ]);
    }
}
