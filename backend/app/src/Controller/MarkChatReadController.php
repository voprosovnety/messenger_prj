<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;

final class MarkChatReadController
{
    #[Route('/api/chats/{chatId}/read', name: 'chat_mark_read', methods: ['POST'])]
    public function __invoke(
        string $chatId,
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
        HubInterface $hub,
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

        $data = json_decode($request->getContent(), true);
        $id = $data['last_read_message_id'] ?? null;

        if (!is_string($id) || $id === '') {
            return new JsonResponse(['error' => 'last_read_message_id is required'], 400);
        }

        // валидируем uuid
        try {
            $lastReadUuid = Uuid::fromString($id);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'invalid uuid'], 400);
        }

        /** @var Message|null $msg */
        $msg = $em->getRepository(Message::class)->find($id);
        if (!$msg || (string) $msg->getChat()?->getId() !== (string) $chat->getId()) {
            return new JsonResponse(['error' => 'message not found'], 404);
        }

        // анти-rollback: нельзя уменьшать lastReadMessageId по времени
        $prev = $membership->getLastReadMessageId();
        if ($prev) {
            $prevMsg = $em->getRepository(Message::class)->find((string) $prev);
            if ($prevMsg && $prevMsg->getCreatedAt() && $msg->getCreatedAt()) {
                if ($msg->getCreatedAt() < $prevMsg->getCreatedAt()) {
                    return new JsonResponse(['error' => 'cannot move last_read backwards'], 409);
                }
            }
        }

        $membership->setLastReadMessageId($lastReadUuid);
        $em->flush();

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());
        $payload = json_encode([
            'type' => 'chat.read',
            'data' => [
                'chat_id' => (string) $chat->getId(),
                'user' => $me->getEmail(),
                'last_read_message_id' => (string) $lastReadUuid,
                'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
        ], JSON_UNESCAPED_SLASHES);

        $hub->publish(new Update($topic, $payload));

        return new JsonResponse(['ok' => true], 200);
    }
}
