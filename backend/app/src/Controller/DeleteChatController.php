<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class DeleteChatController
{
    #[Route('/api/chats/{chatId}', name: 'chat_delete', methods: ['DELETE'])]
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

        $myMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);

        if (!$myMembership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        // group: only OWNER can delete
        if ($chat->isGroup() && $myMembership->getRole() !== 'OWNER') {
            return new JsonResponse(['error' => 'only OWNER can delete group chat'], 403);
        }

        // delete messages
        $em->createQueryBuilder()
            ->delete(Message::class, 'm')
            ->where('m.chat = :chat')
            ->setParameter('chat', $chat)
            ->getQuery()
            ->execute();

        // delete memberships
        $em->createQueryBuilder()
            ->delete(ChatMember::class, 'cm')
            ->where('cm.chat = :chat')
            ->setParameter('chat', $chat)
            ->getQuery()
            ->execute();

        // delete chat
        $em->remove($chat);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
