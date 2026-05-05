<?php

namespace App\Tests\Api;

final class MessagePaginationApiTest extends ApiTestCase
{
    public function testFirstPageReturnsMostRecentMessages(): void
    {
        $user = $this->createUser('pager');
        $chat = $this->createGroupChat($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->createMessage($chat, $user, "msg $i");
        }

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', sprintf('/api/chats/%s/messages?limit=3', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(3, $payload['items']);

        // results are oldest→newest; first page = the 3 most recent (msg3, msg4, msg5)
        self::assertSame('msg 3', $payload['items'][0]['content']);
        self::assertSame('msg 4', $payload['items'][1]['content']);
        self::assertSame('msg 5', $payload['items'][2]['content']);
        self::assertNotNull($payload['next_cursor']);
    }

    public function testCursorReturnsOlderMessages(): void
    {
        $user = $this->createUser('pager2');
        $chat = $this->createGroupChat($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->createMessage($chat, $user, "msg $i");
        }

        $client = $this->createAuthenticatedClient($user);

        // first page
        $client->request('GET', sprintf('/api/chats/%s/messages?limit=3', $chat->getId()));
        $first  = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $cursor = $first['next_cursor'];

        // second page
        $client->request('GET', sprintf(
            '/api/chats/%s/messages?limit=3&before=%s',
            $chat->getId(),
            urlencode($cursor),
        ));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $second = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $second['items']);
        self::assertSame('msg 1', $second['items'][0]['content']);
        self::assertSame('msg 2', $second['items'][1]['content']);
        self::assertNull($second['next_cursor']);
    }

    public function testNextCursorIsNullWhenAllMessagesOnOnePage(): void
    {
        $user = $this->createUser('pager3');
        $chat = $this->createGroupChat($user);

        $this->createMessage($chat, $user, 'only one');

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', sprintf('/api/chats/%s/messages', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $payload['items']);
        self::assertNull($payload['next_cursor']);
    }
}
