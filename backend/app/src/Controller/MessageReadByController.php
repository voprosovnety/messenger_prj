<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class MessageReadByController extends AbstractController
{
    #[Route('/api/chats/{chatId}/messages/{messageId}/read-by', name: 'message_read_by', methods: ['GET'])]
    public function __invoke(
        string $chatId,
        string $messageId,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */

        // 1. Find chat
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }

        // 2. Verify membership
        $myMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $me,
        ]);
        if (!$myMembership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        // 3. Find message
        $message = $em->getRepository(Message::class)->find($messageId);
        if (!$message) {
            return new JsonResponse(['error' => 'message not found'], 404);
        }

        // 4. Verify message belongs to this chat
        if ((string) $message->getChat()->getId() !== (string) $chat->getId()) {
            return new JsonResponse(['error' => 'message not found'], 404);
        }

        // 5. Find members who have read up to or past this message
        $members = $em->getRepository(ChatMember::class)->findBy(['chat' => $chat]);
        $result = [];

        foreach ($members as $member) {
            // Skip the current user
            if ($member->getMember()->getId()->equals($me->getId())) {
                continue;
            }

            $lastReadId = $member->getLastReadMessageId();
            if (!$lastReadId) {
                continue;
            }

            $lastReadMessage = $em->getRepository(Message::class)->find((string) $lastReadId);
            if (!$lastReadMessage) {
                continue;
            }

            // Has this member read up to or past our target message?
            if ($lastReadMessage->getCreatedAt() >= $message->getCreatedAt()) {
                $user = $member->getMember();
                $result[] = [
                    'username'   => $user->getUsername(),
                    'avatar_url' => $user->getAvatarUrl(),
                    'read_at'    => null,
                ];
            }
        }

        return new JsonResponse($result);
    }
}
