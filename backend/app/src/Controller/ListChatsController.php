<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class ListChatsController
{
    #[Route('/api/chats', name: 'chat_list', methods: ['GET'])]
    public function __invoke(Connection $db, UserInterface $me): JsonResponse
    {
        /** @var User $me */
        $meId = (string) $me->getId();

        // Один запрос:
        // - peer для DM через LATERAL
        // - last message через LATERAL
        // - unread_count через LATERAL
        $sql = <<<SQL
SELECT
  c.id::text                                 AS chat_id,
  c.is_group                                 AS is_group,
  c.title                                    AS title,
  c.created_at                               AS chat_created_at,
  cm.role                                    AS my_role,
  cm.joined_at                               AS joined_at,

  peer.username                              AS peer_username,

  CASE
    WHEN c.is_group THEN COALESCE(c.title, 'Group chat')
    ELSE COALESCE(peer.username, 'DM')
  END                                        AS display_name,

  lm.id::text                                AS last_message_id,
  lm.content                                 AS last_message_content,
  lm.created_at                              AS last_message_created_at,
  sender.username                            AS last_message_sender_username,

  COALESCE(uc.unread_count, 0)               AS unread_count

FROM chat_member cm
JOIN chat c ON c.id = cm.chat_id

-- peer для DM (первый участник, который не мы)
LEFT JOIN LATERAL (
  SELECT u2.username
  FROM chat_member cm2
  JOIN "user" u2 ON u2.id = cm2.member_id
  WHERE cm2.chat_id = c.id
    AND cm2.member_id <> cm.member_id
  LIMIT 1
) peer ON true

-- последнее сообщение (игнорируем deleted)
LEFT JOIN LATERAL (
  SELECT m.id, m.content, m.created_at, m.sender_id
  FROM message m
  WHERE m.chat_id = c.id
    AND m.deleted_at IS NULL
  ORDER BY m.created_at DESC, m.id DESC
  LIMIT 1
) lm ON true

LEFT JOIN "user" sender ON sender.id = lm.sender_id

-- unread_count по last_read_message_id (UUIDv7 можно сравнивать как строки/uuid по порядку)
LEFT JOIN LATERAL (
  SELECT COUNT(*)::int AS unread_count
  FROM message m3
  WHERE m3.chat_id = c.id
    AND m3.deleted_at IS NULL
    AND (
      cm.last_read_message_id IS NULL
      OR m3.id > cm.last_read_message_id
    )
) uc ON true

WHERE cm.member_id = :meId

ORDER BY
  COALESCE(lm.created_at, c.created_at) DESC,
  COALESCE(lm.id, c.id) DESC
SQL;

        $rows = $db->fetchAllAssociative($sql, ['meId' => $meId]);

        $items = [];
        foreach ($rows as $r) {
            $isGroup = (bool) $r['is_group'];

            $lastMessage = null;
            if (!empty($r['last_message_id'])) {
                $lastMessage = [
                    'id' => $r['last_message_id'],
                    'content' => $r['last_message_content'],
                    'created_at' => $r['last_message_created_at'] ? (new \DateTimeImmutable($r['last_message_created_at']))->format(DATE_ATOM) : null,
                    'sender_username' => $r['last_message_sender_username'],
                ];
            }

            $items[] = [
                'id' => $r['chat_id'],
                'is_group' => $isGroup,
                'title' => $r['title'],
                'display_name' => $r['display_name'],
                'peer_username' => $r['peer_username'], // null для group
                'created_at' => (new \DateTimeImmutable($r['chat_created_at']))->format(DATE_ATOM),

                // роли: для DM смысла нет → отдаём null
                'my_role' => $isGroup ? $r['my_role'] : null,
                'joined_at' => (new \DateTimeImmutable($r['joined_at']))->format(DATE_ATOM),

                'last_message' => $lastMessage,
                'unread_count' => (int) $r['unread_count'],
            ];
        }

        return new JsonResponse(['items' => $items]);
    }
}
