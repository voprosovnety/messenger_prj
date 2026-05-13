<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class GetUserController
{
    #[Route('/api/users/{username}', name: 'user_get', methods: ['GET'])]
    public function __invoke(
        string $username,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            return new JsonResponse(['error' => 'user not found'], 404);
        }

        $lastSeen = $user->getLastSeenAt();
        $isOnline = $lastSeen && (new \DateTimeImmutable())->getTimestamp() - $lastSeen->getTimestamp() < 65;

        return new JsonResponse([
            'username'     => $user->getUsername(),
            'avatar_url'   => $user->getAvatarUrl(),
            'last_seen_at' => $lastSeen?->format(DATE_ATOM),
            'is_online'    => $isOnline,
            'is_me'        => (string) $user->getId() === (string) $me->getId(),
        ]);
    }
}
