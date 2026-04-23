<?php

namespace App\Tests\Support;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

final class NullHub implements HubInterface
{
    private array $messages = [];

    public function getPublicUrl(): string
    {
        return 'http://test/.well-known/mercure';
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        $this->messages[] = [
            'object' => $update,
            'duration' => 0.0,
            'memory' => 0,
        ];

        return 'test-update-id';
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getDuration(): float
    {
        return 0.0;
    }

    public function getMemory(): int
    {
        return 0;
    }
}
