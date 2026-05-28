<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class DeleteChatController
{
    #[Route('/api/chats/{chatId}', name: 'chat_delete', methods: ['DELETE'])]
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

        // collect member IDs before deleting rows (read-only, stays outside transaction)
        $members = $em->getRepository(ChatMember::class)->findBy(['chat' => $chat]);
        $memberIds = array_map(fn($cm) => (string) $cm->getMember()->getId(), $members);

        // Wrap all deletes + flush in a single transaction.
        $em->wrapInTransaction(function () use ($em, $chat): void {
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
        });

        // Mercure publish stays outside the transaction.
        $deletedPayload = json_encode([
            'type' => 'chat.deleted',
            'data' => ['chat_id' => $chatId],
        ], JSON_UNESCAPED_SLASHES);

        foreach ($memberIds as $memberId) {
            $hub->publish(new Update(sprintf('/users/%s', $memberId), $deletedPayload, true));
        }

        return new JsonResponse(['ok' => true]);
    }
}
