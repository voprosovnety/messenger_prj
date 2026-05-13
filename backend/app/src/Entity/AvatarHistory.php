<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_avatar_history_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_avatar_history_chat', columns: ['chat_id'])]
class AvatarHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Chat $chat = null;

    #[ORM\Column(length: 500)]
    private string $avatarUrl = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): static { $this->user = $u; return $this; }
    public function getChat(): ?Chat { return $this->chat; }
    public function setChat(?Chat $c): static { $this->chat = $c; return $this; }
    public function getAvatarUrl(): string { return $this->avatarUrl; }
    public function setAvatarUrl(string $url): static { $this->avatarUrl = $url; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
