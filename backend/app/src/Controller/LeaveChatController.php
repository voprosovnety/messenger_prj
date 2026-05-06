<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class LeaveChatController
{
    #[Route('/api/chats/{chatId}/leave', name: 'chat_leave', methods: ['POST'])]
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
        if (!$chat->isGroup()) {
            return new JsonResponse(['error' => 'cannot leave a direct chat'], 400);
        }

        $membership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'you are not a member of this chat'], 403);
        }
        if ($membership->getRole() === 'OWNER') {
            return new JsonResponse(['error' => 'owner cannot leave — delete the chat instead'], 400);
        }

        $em->remove($membership);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
