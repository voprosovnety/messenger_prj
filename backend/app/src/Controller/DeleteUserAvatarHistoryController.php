<?php

namespace App\Controller;

use App\Entity\AvatarHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteUserAvatarHistoryController
{
    #[Route('/api/me/avatars/{id}', name: 'me_avatar_history_delete', methods: ['DELETE'])]
    public function __invoke(
        string $id,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        try {
            $uuid = Uuid::fromString($id);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'invalid id'], 400);
        }

        $entry = $em->getRepository(AvatarHistory::class)->find($uuid);
        if (!$entry || $entry->getUser()?->getId() != $me->getId()) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        $em->remove($entry);
        $em->flush();

        return new JsonResponse(null, 204);
    }
}
