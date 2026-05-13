<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\ScheduledMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ScheduledMessageDispatcher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HubInterface $hub,
    ) {}

    /**
     * Convert a ScheduledMessage into a real Message, publish Mercure event, and delete the schedule row.
     * Returns the created Message id (or null if the schedule was invalid and skipped).
     */
    public function dispatch(ScheduledMessage $scheduled): ?string
    {
        $chat = $scheduled->getChat();
        $sender = $scheduled->getSender();
        $content = $scheduled->getContent();
        $attachments = $scheduled->getAttachments();
        $replyTo = $scheduled->getReplyTo();

        if ((trim($content) === '') && !$attachments) {
            // Nothing to deliver; drop it.
            $this->em->remove($scheduled);
            $this->em->flush();
            return null;
        }

        $msg = new Message();
        $msg->setChat($chat);
        $msg->setSender($sender);
        $msg->setContent($content);
        if ($attachments) {
            $msg->setAttachments($attachments);
        }
        if ($replyTo && $replyTo->getDeletedAt() === null) {
            $msg->setReplyTo($replyTo);
        }

        $this->em->persist($msg);
        $this->em->remove($scheduled);
        $this->em->flush();

        $serializeReply = static function (?Message $r): ?array {
            if (!$r) return null;
            return [
                'id'      => (string) $r->getId(),
                'sender'  => $r->getSender()?->getUsername(),
                'content' => $r->getDeletedAt() ? null : $r->getContent(),
                'deleted' => $r->getDeletedAt() !== null,
            ];
        };

        $msgData = [
            'id'                => (string) $msg->getId(),
            'chat_id'           => (string) $chat->getId(),
            'type'              => 'text',
            'sender'            => $sender->getUsername(),
            'sender_avatar_url' => $sender->getAvatarUrl(),
            'content'           => $msg->getContent(),
            'created_at'        => $msg->getCreatedAt()?->format(DATE_ATOM),
            'reply_to'          => $serializeReply($msg->getReplyTo()),
            'forwarded_from'    => null,
            'attachments'       => $msg->getAttachments(),
            'attachment_url'    => null,
            'attachment_type'   => null,
            'attachment_name'   => null,
            'reactions'         => [],
        ];

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());
        $payload = json_encode(['type' => 'message.created', 'data' => $msgData], JSON_UNESCAPED_SLASHES);
        $this->hub->publish(new Update($topic, $payload, true));

        return (string) $msg->getId();
    }

    /**
     * Dispatch all scheduled messages with scheduledAt <= $now.
     * Returns count of dispatched messages.
     */
    public function dispatchDue(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $repo = $this->em->getRepository(ScheduledMessage::class);
        $qb = $repo->createQueryBuilder('sm')
            ->where('sm.scheduledAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('sm.scheduledAt', 'ASC')
            ->setMaxResults(200);
        $due = $qb->getQuery()->getResult();

        $count = 0;
        foreach ($due as $sm) {
            try {
                if ($this->dispatch($sm) !== null) {
                    $count++;
                }
            } catch (\Throwable) {
                // Skip — next run will retry.
            }
        }

        return $count;
    }
}
