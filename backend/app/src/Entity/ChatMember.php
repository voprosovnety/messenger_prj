<?php

namespace App\Entity;

use App\Repository\ChatMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChatMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_chat_member', columns: ['chat_id', 'member_id'])]
class ChatMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Chat $chat = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $member = null;

    #[ORM\Column(length: 20)]
    private string $role = 'MEMBER';

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $lastReadMessageId = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getChat(): ?Chat
    {
        return $this->chat;
    }

    public function setChat(Chat $chat): static
    {
        $this->chat = $chat;

        return $this;
    }

    public function getMember(): ?User
    {
        return $this->member;
    }

    public function setMember(User $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function getLastReadMessageId(): ?Uuid
    {
        return $this->lastReadMessageId;
    }

    public function setLastReadMessageId(?Uuid $lastReadMessageId): static
    {
        $this->lastReadMessageId = $lastReadMessageId;
        return $this;
    }
}
