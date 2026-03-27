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

        $msg = new Message();
        $msg->setChat($chat);
        $msg->setSender($me);
        $msg->setContent($content);

        $em->persist($msg);
        $em->flush();

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());

        $payload = json_encode([
            'type' => 'message.created',
            'data' => [
                'id' => (string) $msg->getId(),
                'chat_id' => (string) $chat->getId(),
                'sender' => $me->getEmail(),
                'content' => $msg->getContent(),
                'created_at' => $msg->getCreatedAt()?->format(DATE_ATOM),
            ],
        ], JSON_UNESCAPED_SLASHES);

        $hub->publish(new Update($topic, $payload));

        return new JsonResponse([
            'id' => (string) $msg->getId(),
            'chat_id' => (string) $chat->getId(),
            'sender' => $me->getEmail(),
            'content' => $msg->getContent(),
            'created_at' => $msg->getCreatedAt()?->format(DATE_ATOM),
        ], 201);
    }
}
