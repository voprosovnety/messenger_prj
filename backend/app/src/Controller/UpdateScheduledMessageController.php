<?php

namespace App\Controller;

use App\Entity\ScheduledMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class UpdateScheduledMessageController
{
    #[Route('/api/scheduled-messages/{id}', name: 'scheduled_message_update', methods: ['PATCH'])]
    public function __invoke(
        string $id,
        Request $request,
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

        $data = json_decode($request->getContent(), true) ?: [];
        $touched = false;

        if (array_key_exists('content', $data)) {
            if (!is_string($data['content'])) {
                return new JsonResponse(['error' => 'invalid content'], 400);
            }
            $sm->setContent($data['content']);
            $touched = true;
        }

        if (array_key_exists('scheduled_at', $data)) {
            if (!is_string($data['scheduled_at'])) {
                return new JsonResponse(['error' => 'invalid scheduled_at'], 400);
            }
            try {
                $newAt = new \DateTimeImmutable($data['scheduled_at']);
            } catch (\Throwable) {
                return new JsonResponse(['error' => 'invalid scheduled_at'], 400);
            }
            $sm->setScheduledAt($newAt);
            $touched = true;
        }

        if (!$touched) {
            return new JsonResponse(['error' => 'no changes'], 400);
        }

        if (trim($sm->getContent()) === '' && !$sm->getAttachments()) {
            return new JsonResponse(['error' => 'content or attachments required'], 400);
        }

        $em->flush();

        return new JsonResponse(CreateScheduledMessageController::serialize($sm));
    }
}
