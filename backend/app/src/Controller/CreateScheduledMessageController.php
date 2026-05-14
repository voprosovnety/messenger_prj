<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\ScheduledMessage;
use App\Entity\User;
use App\Service\ScheduledMessageDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class CreateScheduledMessageController
{
    #[Route('/api/chats/{chatId}/scheduled-messages', name: 'scheduled_message_create', methods: ['POST'])]
    public function __invoke(
        string $chatId,
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
        ScheduledMessageDispatcher $dispatcher,
    ): JsonResponse {
        /** @var User $me */
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }

        $membership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $content = is_string($data['content'] ?? null) ? $data['content'] : '';
        $scheduledAtRaw = $data['scheduled_at'] ?? null;

        $attachments = null;
        if (isset($data['attachments']) && is_array($data['attachments']) && count($data['attachments']) > 0) {
            $attachments = array_values(array_filter(array_map(static function (mixed $a): ?array {
                if (!is_array($a) || !isset($a['url']) || !is_string($a['url'])) return null;
                return [
                    'url'  => $a['url'],
                    'type' => isset($a['type']) && is_string($a['type']) ? $a['type'] : 'file',
                    'name' => isset($a['name']) && is_string($a['name']) ? $a['name'] : null,
                ];
            }, $data['attachments'])));
        }

        if ((trim($content) === '') && !$attachments) {
            return new JsonResponse(['error' => 'content or attachments required'], 400);
        }

        if (!is_string($scheduledAtRaw)) {
            return new JsonResponse(['error' => 'scheduled_at required'], 400);
        }
        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'invalid scheduled_at'], 400);
        }

        $replyToMsg = null;
        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId) {
            $replyToMsg = $em->getRepository(Message::class)->find($replyToId);
            if (!$replyToMsg || (string) $replyToMsg->getChat()->getId() !== $chatId) {
                return new JsonResponse(['error' => 'invalid reply_to_id'], 400);
            }
        }

        $sm = new ScheduledMessage($chat, $me, $scheduledAt);
        $sm->setContent($content);
        $sm->setAttachments($attachments);
        if ($replyToMsg) {
            $sm->setReplyTo($replyToMsg);
        }

        // If scheduled time is already past (or now), dispatch immediately.
        $now = new \DateTimeImmutable();
        if ($scheduledAt <= $now) {
            $em->persist($sm);
            $em->flush();
            $dispatcher->dispatch($sm);
            return new JsonResponse(['dispatched' => true], 201);
        }

        $em->persist($sm);
        $em->flush();

        return new JsonResponse(self::serialize($sm), 201);
    }

    public static function serialize(ScheduledMessage $sm): array
    {
        $reply = $sm->getReplyTo();
        return [
            'id'           => (string) $sm->getId(),
            'chat_id'      => (string) $sm->getChat()->getId(),
            'content'      => $sm->getContent(),
            'attachments'  => $sm->getAttachments(),
            'scheduled_at' => $sm->getScheduledAt()->format(DATE_ATOM),
            'created_at'   => $sm->getCreatedAt()->format(DATE_ATOM),
            'reply_to'     => $reply ? [
                'id'      => (string) $reply->getId(),
                'sender'  => $reply->getSender()?->getUsername(),
                'content' => $reply->getDeletedAt() ? null : $reply->getContent(),
                'deleted' => $reply->getDeletedAt() !== null,
            ] : null,
        ];
    }
}
