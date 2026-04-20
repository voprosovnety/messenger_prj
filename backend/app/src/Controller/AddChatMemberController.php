<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
            return new JsonResponse(['error' => "user not found: $ident"], 404);
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
        $em->flush();

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
