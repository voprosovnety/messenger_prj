<?php

namespace App\Tests\Api;

use App\Entity\Message;

final class MessageApiTest extends ApiTestCase
{
    public function testAuthorCanEditOwnMessage(): void
    {
        $author = $this->createUser('author');
        $chat = $this->createGroupChat($author);
        $message = $this->createMessage($chat, $author, 'before');

        $client = $this->createAuthenticatedClient($author);
        $client->jsonRequest('PATCH', sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()), [
            'content' => 'after',
        ]);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('after', $payload['content']);

        $updated = $this->em->getRepository(Message::class)->find((string) $message->getId());
        self::assertSame('after', $updated?->getContent());
        self::assertNotNull($updated?->getEditedAt());
    }

    public function testNonAuthorCannotEditForeignMessage(): void
    {
        $author = $this->createUser('author');
        $member = $this->createUser('member');
        $chat = $this->createGroupChat($author, [$member]);
        $message = $this->createMessage($chat, $author, 'before');

        $client = $this->createAuthenticatedClient($member);
        $client->jsonRequest('PATCH', sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()), [
            'content' => 'hacked',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAuthorCanDeleteOwnMessage(): void
    {
        $author = $this->createUser('author');
        $chat = $this->createGroupChat($author);
        $message = $this->createMessage($chat, $author, 'bye');

        $client = $this->createAuthenticatedClient($author);
        $client->request('DELETE', sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $deleted = $this->em->getRepository(Message::class)->find((string) $message->getId());
        self::assertNotNull($deleted?->getDeletedAt());
    }

    public function testNonAuthorCannotDeleteForeignMessage(): void
    {
        $author = $this->createUser('author');
        $member = $this->createUser('member');
        $chat = $this->createGroupChat($author, [$member]);
        $message = $this->createMessage($chat, $author, 'bye');

        $client = $this->createAuthenticatedClient($member);
        $client->request('DELETE', sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }
}
