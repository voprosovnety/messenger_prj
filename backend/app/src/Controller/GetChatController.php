<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class GetChatController
{
    #[Route('/api/chats/{chatId}', name: 'chat_get', methods: ['GET'])]
    public function __invoke(
        string $chatId,
        EntityManagerInterface $em,
        UserInterface $me,
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

        $memberships = $em->getRepository(ChatMember::class)->findBy(['chat' => $chat], ['joinedAt' => 'ASC']);
        $participants = [];
        $peerUsername = null;

        foreach ($memberships as $membership) {
            $member = $membership->getMember();
            if (!$member) {
                continue;
            }

            $isMe = (string) $member->getId() === (string) $me->getId();
            if (!$chat->isGroup() && !$isMe) {
                $peerUsername = $member->getUsername();
            }

            $participants[] = [
                'id' => (string) $member->getId(),
                'username' => $member->getUsername(),
                'email' => $member->getEmail(),
                'role' => $membership->getRole(),
                'is_me' => $isMe,
            ];
        }

        return new JsonResponse([
            'id' => (string) $chat->getId(),
            'is_group' => $chat->isGroup(),
            'title' => $chat->getTitle(),
            'display_name' => $chat->isGroup() ? ($chat->getTitle() ?: 'Group chat') : ($peerUsername ?: 'DM'),
            'peer_username' => $peerUsername,
            'my_role' => $chat->isGroup() ? $myMembership->getRole() : null,
            'participants' => $participants,
        ]);
    }
}
