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
use Symfony\Component\Uid\Uuid;

final class DeleteChatAvatarHistoryController
{
    #[Route('/api/chats/{chatId}/avatars/{id}', name: 'chat_avatar_history_delete', methods: ['DELETE'])]
    public function __invoke(
        string $chatId,
        string $id,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }

        $membership = $em->getRepository(ChatMember::class)->findOneBy(['chat' => $chat, 'member' => $me]);
        if (!$membership || $membership->getRole() !== 'OWNER') {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'invalid id'], 400);
        }

        $entry = $em->getRepository(AvatarHistory::class)->find($uuid);
        if (!$entry || $entry->getChat()?->getId() != $chat->getId()) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        $em->remove($entry);
        $em->flush();

        return new JsonResponse(null, 204);
    }
}
