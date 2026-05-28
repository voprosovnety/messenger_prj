<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class AddChatMemberController
{
    #[Route('/api/chats/{chatId}/members', name: 'chat_member_add', methods: ['POST'])]
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
        if (!$chat->isGroup()) {
            return new JsonResponse(['error' => 'cannot add participants to direct chat'], 400);
        }

        $myMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$myMembership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        if ($myMembership->getRole() !== 'OWNER') {
            return new JsonResponse(['error' => 'only OWNER can add participants'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $ident = trim((string) ($data['identifier'] ?? ''));
        if ($ident === '') {
            return new JsonResponse(['error' => 'identifier is required'], 400);
        }

        $user = $em->getRepository(User::class)->findOneBy(['username' => $ident])
            ?? $em->getRepository(User::class)->findOneBy(['email' => $ident]);
        if (!$user) {
            return new JsonResponse(['error' => 'user not found'], 404);
        }

        $existing = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $user,
        ]);
        if ($existing) {
            return new JsonResponse(['error' => 'user is already in chat'], 409);
        }

        $membership = new ChatMember();
        $membership->setChat($chat);
        $membership->setMember($user);
        $membership->setRole('MEMBER');
        $em->persist($membership);

        $sysMsg = new Message();
        $sysMsg->setChat($chat);
        $sysMsg->setType('system');
        $sysMsg->setContent($me->getUsername() . ' added ' . $user->getUsername() . ' to the group');
        $em->persist($sysMsg);

        $em->flush();

        $chatTopic = sprintf('/chats/%s/messages', (string) $chat->getId());
        $sysMsgData = [
            'id'         => (string) $sysMsg->getId(),
            'chat_id'    => (string) $chat->getId(),
            'type'       => 'system',
            'sender'     => null,
            'content'    => $sysMsg->getContent(),
            'created_at' => $sysMsg->getCreatedAt()->format(DATE_ATOM),
        ];
        $hub->publish(new Update($chatTopic, json_encode(['type' => 'message.created', 'data' => $sysMsgData], JSON_UNESCAPED_SLASHES), true));

        $newMemberTopic = sprintf('/users/%s', (string) $user->getId());
        $hub->publish(new Update($newMemberTopic, json_encode([
            'type' => 'chat.created',
            'data' => ['chat_id' => (string) $chat->getId()],
        ], JSON_UNESCAPED_SLASHES), true));

        return new JsonResponse([
            'ok' => true,
            'participant' => [
                'id' => (string) $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'role' => $membership->getRole(),
                'is_me' => (string) $user->getId() === (string) $me->getId(),
            ],
        ], 201);
    }
}
