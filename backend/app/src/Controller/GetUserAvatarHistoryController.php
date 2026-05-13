<?php

namespace App\Controller;

use App\Entity\AvatarHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class GetUserAvatarHistoryController
{
    #[Route('/api/me/avatars', name: 'me_avatar_history', methods: ['GET'])]
    public function __invoke(
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $rows = $em->getRepository(AvatarHistory::class)->findBy(
            ['user' => $me],
            ['createdAt' => 'DESC'],
            20,
        );

        return new JsonResponse([
            'items' => array_map(
                static fn(AvatarHistory $h) => ['url' => $h->getAvatarUrl(), 'created_at' => $h->getCreatedAt()->format(DATE_ATOM)],
                $rows,
            ),
        ]);
    }
}
