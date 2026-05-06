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

final class CreateMessageController
{
    #[Route('/api/chats/{chatId}/messages', name: 'message_create', methods: ['POST'])]
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

        // проверяем членство
        $membership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return new JsonResponse(['error' => 'content is required'], 400);
        }

        $replyToMsg = null;
        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId) {
            $replyToMsg = $em->getRepository(Message::class)->find($replyToId);
            if (!$replyToMsg || (string) $replyToMsg->getChat()->getId() !== $chatId) {
                return new JsonResponse(['error' => 'invalid reply_to_id'], 400);
            }
        }

        $msg = new Message();
        $msg->setChat($chat);
        $msg->setSender($me);
        $msg->setContent($content);
        if ($replyToMsg) {
            $msg->setReplyTo($replyToMsg);
        }

        $em->persist($msg);
        $em->flush();

        $serializeReply = static function (?Message $r): ?array {
            if (!$r) return null;
            return [
                'id'      => (string) $r->getId(),
                'sender'  => $r->getSender()?->getUsername(),
                'content' => $r->getDeletedAt() ? null : $r->getContent(),
                'deleted' => $r->getDeletedAt() !== null,
            ];
        };

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());

        $msgData = [
            'id'              => (string) $msg->getId(),
            'chat_id'         => (string) $chat->getId(),
            'sender'          => $me->getUsername(),
            'sender_avatar_url' => $me->getAvatarUrl(),
            'content'         => $msg->getContent(),
            'created_at'      => $msg->getCreatedAt()?->format(DATE_ATOM),
            'reply_to'        => $serializeReply($replyToMsg),
        ];

        $payload = json_encode(['type' => 'message.created', 'data' => $msgData], JSON_UNESCAPED_SLASHES);
        $hub->publish(new Update($topic, $payload, true));

        return new JsonResponse($msgData, 201);
    }
}
