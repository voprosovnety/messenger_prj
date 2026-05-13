<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class SearchMessagesController
{
    #[Route('/api/chats/{chatId}/messages/search', name: 'message_search', methods: ['GET'])]
    public function __invoke(
        string $chatId,
        EntityManagerInterface $em,
        UserInterface $me,
        Request $request,
    ): JsonResponse {
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

        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return new JsonResponse(['items' => []]);
        }

        $rows = $em->createQueryBuilder()
            ->select('m', 's')
            ->from(Message::class, 'm')
            ->leftJoin('m.sender', 's')
            ->where('m.chat = :chat')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('LOWER(m.content) LIKE LOWER(:q)')
            ->setParameter('chat', $chat)
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();

        $items = array_map(static fn(Message $m) => [
            'id'                => (string) $m->getId(),
            'sender'            => $m->getSender()?->getUsername(),
            'sender_avatar_url' => $m->getSender()?->getAvatarUrl(),
            'content'           => $m->getContent(),
            'created_at'        => $m->getCreatedAt()->format(DATE_ATOM),
        ], $rows);

        return new JsonResponse(['items' => $items]);
    }
}
