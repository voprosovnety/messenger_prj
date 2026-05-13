<?php

namespace App\Controller;

use App\Entity\ChatMember;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class GlobalSearchMessagesController
{
    #[Route('/api/messages/search', name: 'message_search_global', methods: ['GET'])]
    public function __invoke(
        EntityManagerInterface $em,
        UserInterface $me,
        Request $request,
    ): JsonResponse {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return new JsonResponse(['items' => []]);
        }

        $limit = (int) $request->query->get('limit', 30);
        $limit = max(1, min(50, $limit));

        $memberships = $em->getRepository(ChatMember::class)->findBy(['member' => $me]);
        if (!$memberships) {
            return new JsonResponse(['items' => []]);
        }
        $chatIds = array_map(static fn(ChatMember $cm) => $cm->getChat()->getId(), $memberships);

        $rows = $em->createQueryBuilder()
            ->select('m', 's', 'c')
            ->from(Message::class, 'm')
            ->leftJoin('m.sender', 's')
            ->innerJoin('m.chat', 'c')
            ->where('m.chat IN (:chats)')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('LOWER(m.content) LIKE LOWER(:q)')
            ->setParameter('chats', $chatIds)
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // For DM chats in the result set, find peer (other member) to use as display name.
        $dmChatIds = [];
        foreach ($rows as $m) {
            $chat = $m->getChat();
            if (!$chat->isGroup()) {
                $dmChatIds[(string) $chat->getId()] = $chat->getId();
            }
        }

        $dmPeerByChatId = [];
        if ($dmChatIds) {
            $peerMembers = $em->createQueryBuilder()
                ->select('cm', 'u')
                ->from(ChatMember::class, 'cm')
                ->leftJoin('cm.member', 'u')
                ->where('cm.chat IN (:chats)')
                ->andWhere('cm.member != :me')
                ->setParameter('chats', array_values($dmChatIds))
                ->setParameter('me', $me)
                ->getQuery()
                ->getResult();
            foreach ($peerMembers as $cm) {
                $cid = (string) $cm->getChat()->getId();
                if (!isset($dmPeerByChatId[$cid])) {
                    $dmPeerByChatId[$cid] = $cm->getMember();
                }
            }
        }

        $items = array_map(static function (Message $m) use ($dmPeerByChatId) {
            $chat = $m->getChat();
            $chatId = (string) $chat->getId();
            $isGroup = $chat->isGroup();
            $peer = $dmPeerByChatId[$chatId] ?? null;

            return [
                'id'                => (string) $m->getId(),
                'chat_id'           => $chatId,
                'chat_is_group'     => $isGroup,
                'chat_display_name' => $isGroup
                    ? ($chat->getTitle() ?? 'Group chat')
                    : ($peer?->getUsername() ?? 'DM'),
                'chat_avatar_url'   => $isGroup ? $chat->getAvatarUrl() : $peer?->getAvatarUrl(),
                'sender'            => $m->getSender()?->getUsername(),
                'sender_avatar_url' => $m->getSender()?->getAvatarUrl(),
                'content'           => $m->getContent(),
                'created_at'        => $m->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $rows);

        return new JsonResponse(['items' => $items]);
    }
}
