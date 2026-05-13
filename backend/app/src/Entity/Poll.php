<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Poll
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Message $message = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $question = '';

    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\Column]
    private bool $multipleAnswers = false;

    #[ORM\Column]
    private bool $anonymous = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getMessage(): ?Message { return $this->message; }
    public function setMessage(Message $message): static { $this->message = $message; return $this; }

    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $question): static { $this->question = $question; return $this; }

    public function getOptions(): array { return $this->options; }
    public function setOptions(array $options): static { $this->options = $options; return $this; }

    public function isMultipleAnswers(): bool { return $this->multipleAnswers; }
    public function setMultipleAnswers(bool $v): static { $this->multipleAnswers = $v; return $this; }

    public function isAnonymous(): bool { return $this->anonymous; }
    public function setAnonymous(bool $v): static { $this->anonymous = $v; return $this; }
}
