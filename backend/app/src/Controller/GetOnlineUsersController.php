<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class GetOnlineUsersController
{
    #[Route('/api/users/online', name: 'users_online', methods: ['GET'])]
    public function __invoke(
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $threshold = new \DateTimeImmutable('-65 seconds');

        $users = $em->createQuery(
            'SELECT u FROM App\Entity\User u WHERE u.lastSeenAt >= :threshold AND u.id != :myId ORDER BY u.lastSeenAt DESC'
        )
            ->setParameter('threshold', $threshold)
            ->setParameter('myId', $me->getId())
            ->getResult();

        return new JsonResponse(array_map(fn(User $u) => [
            'username'     => $u->getUsername(),
            'avatar_url'   => $u->getAvatarUrl(),
            'last_seen_at' => $u->getLastSeenAt()?->format(DATE_ATOM),
            'is_online'    => true,
        ], $users));
    }
}
