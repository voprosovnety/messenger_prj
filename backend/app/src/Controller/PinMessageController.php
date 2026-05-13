<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\PinnedMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class PinMessageController
{
    #[Route('/api/chats/{chatId}/pin', name: 'chat_pin', methods: ['POST'])]
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

        $myMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$myMembership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        if ($chat->isGroup() && $myMembership->getRole() !== 'OWNER') {
            return new JsonResponse(['error' => 'only OWNER can pin messages in group chats'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $messageId = $data['message_id'] ?? null;

        if (!$messageId) {
            return new JsonResponse(['error' => 'message_id is required'], 400);
        }

        $msg = $em->getRepository(Message::class)->find($messageId);
        if (!$msg || (string) $msg->getChat()?->getId() !== $chatId) {
            return new JsonResponse(['error' => 'message not found'], 404);
        }
        if ($msg->getDeletedAt() !== null) {
            return new JsonResponse(['error' => 'cannot pin a deleted message'], 409);
        }

        $existing = $em->getRepository(PinnedMessage::class)->findOneBy([
            'chat' => $chat,
            'message' => $msg,
        ]);

        if ($existing) {
            $em->remove($existing);
        } else {
            $em->persist(new PinnedMessage($chat, $msg));
        }
        $em->flush();

        $pinnedMessages = self::buildPinnedList($em, $chat);

        $topic = sprintf('/chats/%s/messages', $chatId);
        $hub->publish(new Update($topic, json_encode([
            'type' => 'message.pinned',
            'data' => [
                'chat_id'         => $chatId,
                'pinned_messages' => $pinnedMessages,
            ],
        ], JSON_UNESCAPED_SLASHES), true));

        return new JsonResponse(['pinned_messages' => $pinnedMessages]);
    }

    public static function buildPinnedList(EntityManagerInterface $em, Chat $chat): array
    {
        $pins = $em->getRepository(PinnedMessage::class)->findBy(['chat' => $chat]);
        usort($pins, fn($a, $b) =>
            $a->getMessage()->getCreatedAt() <=> $b->getMessage()->getCreatedAt()
        );
        return array_map(function (PinnedMessage $pm) {
            $msg = $pm->getMessage();
            return [
                'id'              => (string) $msg->getId(),
                'sender'          => $msg->getSender()?->getUsername(),
                'content'         => $msg->getContent(),
                'attachments'     => $msg->getAttachments(),
                'attachment_url'  => $msg->getAttachmentUrl(),
                'attachment_type' => $msg->getAttachmentType(),
                'attachment_name' => $msg->getAttachmentName(),
                'created_at'      => $msg->getCreatedAt()->format(DATE_ATOM),
                'pinned_at'       => $pm->getPinnedAt()->format(DATE_ATOM),
            ];
        }, $pins);
    }
}
