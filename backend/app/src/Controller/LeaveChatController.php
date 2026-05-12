<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class LeaveChatController
{
    #[Route('/api/chats/{chatId}/leave', name: 'chat_leave', methods: ['POST'])]
    public function __invoke(
        string $chatId,
        EntityManagerInterface $em,
        UserInterface $me,
        HubInterface $hub,
    ): JsonResponse {
        /** @var User $me */
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }
        if (!$chat->isGroup()) {
            return new JsonResponse(['error' => 'cannot leave a direct chat'], 400);
        }

        $membership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'you are not a member of this chat'], 403);
        }
        if ($membership->getRole() === 'OWNER') {
            return new JsonResponse(['error' => 'owner cannot leave — delete the chat instead'], 400);
        }

        $em->remove($membership);

        $sysMsg = new Message();
        $sysMsg->setChat($chat);
        $sysMsg->setType('system');
        $sysMsg->setContent($me->getUsername() . ' left the group');
        $em->persist($sysMsg);

        $em->flush();

        $chatTopic = sprintf('/chats/%s/messages', $chatId);
        $sysMsgData = [
            'id'         => (string) $sysMsg->getId(),
            'chat_id'    => $chatId,
            'type'       => 'system',
            'sender'     => null,
            'content'    => $sysMsg->getContent(),
            'created_at' => $sysMsg->getCreatedAt()->format(DATE_ATOM),
        ];
        $hub->publish(new Update($chatTopic, json_encode(['type' => 'message.created', 'data' => $sysMsgData], JSON_UNESCAPED_SLASHES), true));

        $myId = (string) $me->getId();
        $hub->publish(new Update($chatTopic, json_encode([
            'type' => 'chat.member_removed',
            'data' => ['chat_id' => $chatId, 'user_id' => $myId],
        ], JSON_UNESCAPED_SLASHES), true));

        $hub->publish(new Update(sprintf('/users/%s', $myId), json_encode([
            'type' => 'chat.deleted',
            'data' => ['chat_id' => $chatId],
        ], JSON_UNESCAPED_SLASHES), true));

        return new JsonResponse(['ok' => true]);
    }
}
