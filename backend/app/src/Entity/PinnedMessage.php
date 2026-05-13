<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_chat_message_pin', columns: ['chat_id', 'message_id'])]
class PinnedMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Chat $chat;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column]
    private \DateTimeImmutable $pinnedAt;

    public function __construct(Chat $chat, Message $message)
    {
        $this->id = Uuid::v7();
        $this->chat = $chat;
        $this->message = $message;
        $this->pinnedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getChat(): Chat { return $this->chat; }
    public function getMessage(): Message { return $this->message; }
    public function getPinnedAt(): \DateTimeImmutable { return $this->pinnedAt; }
}
