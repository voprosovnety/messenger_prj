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
        $description = $data['description'] ?? null;
        $participants = $data['participants'] ?? [];

        if (!is_array($participants)) {
            return new JsonResponse(['error' => 'participants must be array of usernames/emails'], 400);
        }

        if ($isGroup) {
            if (!$title || !is_string($title)) {
                return new JsonResponse(['error' => 'title is required for group chat'], 400);
            }
            if (count($participants) < 1) {
                return new JsonResponse(['error' => 'group chat requires at least 1 participant'], 400);
            }
        } else {
            if (count($participants) !== 1) {
                return new JsonResponse(['error' => 'for 1:1 chat provide exactly 1 participant'], 400);
            }
        }

        if (!$isGroup) {
            $ident = trim((string) $participants[0]);

            $peer = $em->getRepository(User::class)->findOneBy(['username' => $ident])
                ?? $em->getRepository(User::class)->findOneBy(['email' => $ident]);

            if (!$peer) {
                return new JsonResponse(['error' => "user not found: $ident"], 404);
            }

            if ((string)$peer->getId() === (string)$me->getId()) {
                return new JsonResponse(['error' => 'cannot create DM with yourself'], 400);
            }

            $existing = $em->createQueryBuilder()
                ->select('c')
                ->from(Chat::class, 'c')
                ->join(ChatMember::class, 'cmMe', 'WITH', 'cmMe.chat = c')
                ->join(ChatMember::class, 'cmPeer', 'WITH', 'cmPeer.chat = c')
                ->where('c.isGroup = false')
                ->andWhere('cmMe.member = :me')
                ->andWhere('cmPeer.member = :peer')
                ->setParameter('me', $me)
                ->setParameter('peer', $peer)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                return new JsonResponse([
                    'id' => (string) $existing->getId(),
                    'is_group' => false,
                    'title' => null,
                    'already_exists' => true,
                ], 200);
            }
        }

        $chat = new Chat();
        $chat->setIsGroup($isGroup);
        $chat->setTitle($isGroup ? $title : null);
        $chat->setDescription($description !== '' ? $description : null);
        $em->persist($chat);

        // creator = OWNER
        $owner = new ChatMember();
        $owner->setChat($chat);
        $owner->setMember($me);
        $owner->setRole('OWNER');
        $em->persist($owner);

        foreach ($participants as $ident) {
            if (!is_string($ident) || trim($ident) === '') {
                continue;
            }

            $ident = trim($ident);


            if ($ident === $me->getEmail() || $ident === $me->getUsername()) {
                continue;
            }

            $u = $em->getRepository(User::class)->findOneBy(['username' => $ident])
                ?? $em->getRepository(User::class)->findOneBy(['email' => $ident]);

            if (!$u) {
                return new JsonResponse(['error' => "user not found: $ident"], 404);
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
