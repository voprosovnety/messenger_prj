<?php

namespace App\Controller;

use App\Entity\ScheduledMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class DeleteScheduledMessageController
{
    #[Route('/api/scheduled-messages/{id}', name: 'scheduled_message_delete', methods: ['DELETE'])]
    public function __invoke(
        string $id,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $sm = $em->getRepository(ScheduledMessage::class)->find($id);
        if (!$sm) {
            return new JsonResponse(['error' => 'not found'], 404);
        }
        if (!$sm->getSender()->getId()?->equals($me->getId())) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $em->remove($sm);
        $em->flush();

        return new JsonResponse(['deleted' => true]);
    }
}
