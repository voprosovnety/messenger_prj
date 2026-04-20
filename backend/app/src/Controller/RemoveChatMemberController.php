<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class RemoveChatMemberController
{
    #[Route('/api/chats/{chatId}/members/{userId}', name: 'chat_member_remove', methods: ['DELETE'])]
    public function __invoke(
        string $chatId,
        string $userId,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }
        if (!$chat->isGroup()) {
            return new JsonResponse(['error' => 'cannot remove participants from direct chat'], 400);
        }

        $myMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$myMembership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        if ($myMembership->getRole() !== 'OWNER') {
            return new JsonResponse(['error' => 'only OWNER can remove participants'], 403);
        }

        $target = $em->getRepository(User::class)->find($userId);
        if (!$target) {
            return new JsonResponse(['error' => 'user not found'], 404);
        }

        $targetMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $target,
        ]);
        if (!$targetMembership) {
            return new JsonResponse(['error' => 'participant not found in chat'], 404);
        }
        if ($targetMembership->getRole() === 'OWNER') {
            return new JsonResponse(['error' => 'cannot remove chat owner'], 400);
        }

        $em->remove($targetMembership);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
