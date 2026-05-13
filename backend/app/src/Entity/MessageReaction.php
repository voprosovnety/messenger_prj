<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_reaction', columns: ['message_id', 'user_id', 'emoji'])]
class MessageReaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Message $message = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 32)]
    private string $emoji = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getMessage(): ?Message { return $this->message; }
    public function setMessage(Message $m): static { $this->message = $m; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $u): static { $this->user = $u; return $this; }
    public function getEmoji(): string { return $this->emoji; }
    public function setEmoji(string $e): static { $this->emoji = $e; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
