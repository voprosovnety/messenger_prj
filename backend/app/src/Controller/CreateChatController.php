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

final class CreateChatController
{
    #[Route('/api/chats', name: 'chat_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $data = json_decode($request->getContent(), true);

        $isGroup = (bool) ($data['is_group'] ?? false);
        $title = $data['title'] ?? null;
        $emails = $data['participants'] ?? [];

        if (!is_array($emails)) {
            return new JsonResponse(['error' => 'participants must be array of emails'], 400);
        }

        if ($isGroup) {
            if (!$title || !is_string($title)) {
                return new JsonResponse(['error' => 'title is required for group chat'], 400);
            }
            if (count($emails) < 1) {
                return new JsonResponse(['error' => 'group chat requires at least 1 participant'], 400);
            }
        } else {
            if (count($emails) !== 1) {
                return new JsonResponse(['error' => 'for 1:1 chat provide exactly 1 participant'], 400);
            }
        }

        $chat = new Chat();
        $chat->setIsGroup($isGroup);
        $chat->setTitle($isGroup ? $title : null);
        $em->persist($chat);

        // creator = OWNER
        $owner = new ChatMember();
        $owner->setChat($chat);
        $owner->setMember($me);
        $owner->setRole('OWNER');
        $em->persist($owner);

        foreach ($emails as $email) {
            if (!is_string($email) || $email === $me->getEmail()) {
                continue;
            }

            $u = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$u) {
                return new JsonResponse(['error' => "user not found: $email"], 404);
            }

            $m = new ChatMember();
            $m->setChat($chat);
            $m->setMember($u);
            $m->setRole('MEMBER');
            $em->persist($m);
        }

        $em->flush();

        return new JsonResponse([
            'id' => (string) $chat->getId(),
            'is_group' => $chat->isGroup(),
            'title' => $chat->getTitle(),
        ], 201);
    }
}
