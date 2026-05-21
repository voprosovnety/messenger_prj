<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class ChatMediaController extends AbstractController
{
    #[Route('/api/chats/{chatId}/media', name: 'chat_media', methods: ['GET'])]
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
            'chat'   => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        // Fetch non-deleted, non-system, non-poll messages that carry attachments,
        // newest first. We over-fetch slightly at the DB level then cap at 200 items
        // after expanding the attachments array (one message can have many items).
        $qb = $em->createQueryBuilder();
        $qb->select('m', 's')
            ->from(Message::class, 'm')
            ->leftJoin('m.sender', 's')
            ->where('m.chat = :chat')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.type NOT IN (:skipTypes)')
            ->andWhere('(m.attachments IS NOT NULL OR m.attachmentUrl IS NOT NULL)')
            ->setParameter('chat', $chat)
            ->setParameter('skipTypes', ['system', 'poll'])
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        /** @var Message[] $rows */
        $rows = $qb->getQuery()->getResult();

        $items = [];
        foreach ($rows as $message) {
            if (count($items) >= 200) {
                break;
            }

            $messageId  = (string) $message->getId();
            $senderName = $message->getSender()?->getUsername();
            $createdAt  = $message->getCreatedAt()->format(DATE_ATOM);

            $attachments = $message->getAttachments();

            if (!empty($attachments)) {
                // New-style: one item per element in the JSON array
                foreach ($attachments as $att) {
                    if (count($items) >= 200) {
                        break;
                    }
                    $items[] = [
                        'url'        => $att['url']  ?? null,
                        'type'       => $att['type'] ?? null,
                        'name'       => $att['name'] ?? null,
                        'sender'     => $senderName,
                        'created_at' => $createdAt,
                        'message_id' => $messageId,
                    ];
                }
            } elseif ($message->getAttachmentUrl() !== null) {
                // Legacy single-attachment columns
                $items[] = [
                    'url'        => $message->getAttachmentUrl(),
                    'type'       => $message->getAttachmentType(),
                    'name'       => $message->getAttachmentName(),
                    'sender'     => $senderName,
                    'created_at' => $createdAt,
                    'message_id' => $messageId,
                ];
            }
            // If neither is set the message slipped through the WHERE clause
            // (shouldn't happen) — silently skip it.
        }

        return new JsonResponse(['items' => $items]);
    }
}
