<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'scheduled_message')]
#[ORM\Index(name: 'idx_scheduled_message_due', columns: ['scheduled_at'])]
#[ORM\Index(name: 'idx_scheduled_message_chat_sender', columns: ['chat_id', 'sender_id'])]
class ScheduledMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Chat $chat;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attachments = null;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Message $replyTo = null;

    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Chat $chat, User $sender, \DateTimeImmutable $scheduledAt)
    {
        $this->id = Uuid::v7();
        $this->chat = $chat;
        $this->sender = $sender;
        $this->scheduledAt = $scheduledAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getChat(): Chat { return $this->chat; }
    public function getSender(): User { return $this->sender; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getAttachments(): ?array { return $this->attachments; }
    public function setAttachments(?array $attachments): static { $this->attachments = $attachments; return $this; }

    public function getReplyTo(): ?Message { return $this->replyTo; }
    public function setReplyTo(?Message $replyTo): static { $this->replyTo = $replyTo; return $this; }

    public function getScheduledAt(): \DateTimeImmutable { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static { $this->scheduledAt = $scheduledAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
