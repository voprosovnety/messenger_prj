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

final class EditMessageController
{
    #[Route('/api/chats/{chatId}/messages/{messageId}', name: 'message_edit', methods: ['PATCH'])]
    public function __invoke(
        string $chatId,
        string $messageId,
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

        $msg = $em->getRepository(Message::class)->find($messageId);
        if (!$msg || (string)$msg->getChat()?->getId() !== (string)$chat->getId()) {
            return new JsonResponse(['error' => 'message not found'], 404);
        }

        // право редактирования: только автор
        if ((string) $msg->getSender()?->getId() !== (string) $me->getId()) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        if ($msg->getDeletedAt() !== null) {
            return new JsonResponse(['error' => 'message deleted'], 409);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return new JsonResponse(['error' => 'content is required'], 400);
        }

        $msg->setContent($content);
        $msg->setEditedAt(new \DateTimeImmutable());

        $em->flush();

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());
        $payload = json_encode([
            'type' => 'message.edited',
            'data' => [
                'id' => (string) $msg->getId(),
                'chat_id' => (string) $chat->getId(),
                'content' => $msg->getContent(),
                'edited_at' => $msg->getEditedAt()?->format(DATE_ATOM),
            ],
        ], JSON_UNESCAPED_SLASHES);

        $hub->publish(new Update($topic, $payload, true));

        return new JsonResponse([
            'id' => (string) $msg->getId(),
            'chat_id' => (string) $chat->getId(),
            'content' => $msg->getContent(),
            'edited_at' => $msg->getEditedAt()?->format(DATE_ATOM),
        ], 200);
    }
}
