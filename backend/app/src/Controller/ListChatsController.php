<?php

namespace App\Controller;

use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class ListChatsController
{
    #[Route('/api/chats', name: 'chat_list', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em, UserInterface $me): JsonResponse
    {
        /** @var User $me */
        $qb = $em->createQueryBuilder();

        /** @var ChatMember[] $memberships */
        $memberships = $qb
            ->select('cm', 'c')
            ->from(ChatMember::class, 'cm')
            ->join('cm.chat', 'c')
            ->where('cm.member = :me')
            ->setParameter('me', $me)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($memberships as $cm) {
            $chat = $cm->getChat();

            $items[] = [
                'id' => (string) $chat->getId(),
                'is_group' => $chat->isGroup(),
                'title' => $chat->getTitle(),
                'created_at' => $chat->getCreatedAt()?->format(DATE_ATOM),
                'my_role' => $cm->getRole(),
                'joined_at' => $cm->getJoinedAt()?->format(DATE_ATOM),
            ];
        }

        return new JsonResponse(['items' => $items]);
    }
}
