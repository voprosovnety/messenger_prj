<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class MeController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(?UserInterface $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        /** @var \App\Entity\User $user */
        return new JsonResponse([
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ]);
    }
}
