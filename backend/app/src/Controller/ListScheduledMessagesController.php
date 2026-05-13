<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\ScheduledMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class ListScheduledMessagesController
{
    #[Route('/api/chats/{chatId}/scheduled-messages', name: 'scheduled_message_list', methods: ['GET'])]
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

        $rows = $em->getRepository(ScheduledMessage::class)->createQueryBuilder('sm')
            ->where('sm.chat = :chat')
            ->andWhere('sm.sender = :me')
            ->setParameter('chat', $chat)
            ->setParameter('me', $me)
            ->orderBy('sm.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();

        $items = array_map([CreateScheduledMessageController::class, 'serialize'], $rows);

        return new JsonResponse(['items' => $items]);
    }
}
