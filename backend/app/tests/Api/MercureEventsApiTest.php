<?php

namespace App\Tests\Api;

final class MercureEventsApiTest extends ApiTestCase
{
    public function testCreateMessagePublishesMercureEvent(): void
    {
        $user   = $this->createUser('mercure_user');
        $chat   = $this->createGroupChat($user);
        $client = $this->createAuthenticatedClient($user);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages', $chat->getId()), [
            'content' => 'hello mercure',
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());
        self::assertSame(1, $this->hub->count());

        $update  = $this->hub->getMessages()[0]['object'];
        $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('message.created', $payload['type']);
        self::assertSame('hello mercure', $payload['data']['content']);
        self::assertSame('mercure_user', $payload['data']['sender']);
        self::assertSame((string) $chat->getId(), $payload['data']['chat_id']);
    }

    public function testEditMessagePublishesMercureEvent(): void
    {
        $user    = $this->createUser('mercure_editor');
        $chat    = $this->createGroupChat($user);
        $message = $this->createMessage($chat, $user, 'original');
        $client  = $this->createAuthenticatedClient($user);

        $client->jsonRequest(
            'PATCH',
            sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()),
            ['content' => 'edited'],
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame(1, $this->hub->count());

        $update  = $this->hub->getMessages()[0]['object'];
        $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('message.edited', $payload['type']);
        self::assertSame('edited', $payload['data']['content']);
        self::assertSame((string) $message->getId(), $payload['data']['id']);
    }

    public function testDeleteMessagePublishesMercureEvent(): void
    {
        $user    = $this->createUser('mercure_deleter');
        $chat    = $this->createGroupChat($user);
        $message = $this->createMessage($chat, $user, 'bye');
        $client  = $this->createAuthenticatedClient($user);

        $client->request(
            'DELETE',
            sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame(1, $this->hub->count());

        $update  = $this->hub->getMessages()[0]['object'];
        $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('message.deleted', $payload['type']);
        self::assertSame((string) $message->getId(), $payload['data']['id']);
        self::assertArrayHasKey('deleted_at', $payload['data']);
    }
}
