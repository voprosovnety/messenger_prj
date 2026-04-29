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
use Symfony\Component\HttpFoundation\Request;

final class ListMessagesController
{
    #[Route('/api/chats/{chatId}/messages', name: 'message_list', methods: ['GET'])]
    public function __invoke(
        string $chatId,
        EntityManagerInterface $em,
        UserInterface $me,
        Request $request,
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

        $peerDeliveredId = null;
        $peerReadId = null;
        $memberships = $em->getRepository(ChatMember::class)->findBy(['chat' => $chat]);
        foreach ($memberships as $chatMember) {
            if ($chatMember->getMember()?->getId()?->equals($me->getId())) {
                continue;
            }

            $deliveredId = $chatMember->getLastDeliveredMessageId();
            if ($deliveredId && (!$peerDeliveredId || (string) $deliveredId > $peerDeliveredId)) {
                $peerDeliveredId = (string) $deliveredId;
            }

            $readId = $chatMember->getLastReadMessageId();
            if ($readId && (!$peerReadId || (string) $readId > $peerReadId)) {
                $peerReadId = (string) $readId;
            }
        }

        $limit = (int) $request->query->get('limit', 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 100) $limit = 100;

        $before = $request->query->get('before'); // e.g. 2026-03-17T09:15:37+00:00|019c...
        $beforeDt = null;
        $beforeId = null;

        if (is_string($before) && str_contains($before, '|')) {
            [$dtStr, $idStr] = explode('|', $before, 2);
            try {
                $beforeDt = new \DateTimeImmutable($dtStr);
                $beforeId = $idStr;
            } catch (\Throwable) {
                return new JsonResponse(['error' => 'invalid before cursor'], 400);
            }
        }

        $qb = $em->createQueryBuilder();
        $qb
            ->select('m', 's')
            ->from(Message::class, 'm')
            ->join('m.sender', 's')
            ->where('m.chat = :chat')
            ->setParameter('chat', $chat)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($limit + 1); // +1 чтобы понять, есть ли следующая страница

        if ($beforeDt && $beforeId) {
            // (createdAt, id) строго меньше курсора
            $qb->andWhere('(m.createdAt < :beforeDt) OR (m.createdAt = :beforeDt AND m.id < :beforeId)')
                ->setParameter('beforeDt', $beforeDt)
                ->setParameter('beforeId', $beforeId);
        }

        $rows = $qb->getQuery()->getResult();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        // сейчас rows идут новые->старые, отдаём старые->новые
        $rows = array_reverse($rows);

        $items = [];
        foreach ($rows as $m) {
            $items[] = [
                'id' => (string) $m->getId(),
                'sender' => $m->getSender()->getUsername(),
                'sender_avatar_url' => $m->getSender()->getAvatarUrl(),
                'content' => $m->getContent(),
                'created_at' => $m->getCreatedAt()->format(DATE_ATOM),
                'edited_at' => $m->getEditedAt()?->format(DATE_ATOM),
                'deleted_at' => $m->getDeletedAt()?->format(DATE_ATOM),
            ];
        }

        $nextCursor = null;
        if ($hasMore && $rows) {
            $oldest = $rows[0]; // самый старый в текущей странице
            $nextCursor = $oldest->getCreatedAt()->format(DATE_ATOM) . '|' . (string) $oldest->getId();
        }

        return new JsonResponse([
            'items' => $items,
            'next_cursor' => $nextCursor,
            'peer_delivered_message_id' => $peerDeliveredId,
            'peer_read_message_id' => $peerReadId,
        ]);
    }
}
