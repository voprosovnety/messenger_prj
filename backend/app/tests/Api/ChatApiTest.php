<?php

namespace App\Tests\Api;

use App\Entity\ChatMember;

/**
 * Tests for chat CRUD endpoints.
 * GET /api/chats (list) is intentionally not tested here: it uses
 * PostgreSQL-specific LATERAL joins and cannot run on the SQLite test database.
 */
final class ChatApiTest extends ApiTestCase
{
    public function testCreateGroupChatSucceeds(): void
    {
        $owner  = $this->createUser('groupowner');
        $member = $this->createUser('groupmember');
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', '/api/chats', [
            'is_group'     => true,
            'title'        => 'My Group',
            'participants' => ['groupmember'],
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($payload['is_group']);
        self::assertSame('My Group', $payload['title']);
        self::assertArrayHasKey('id', $payload);

        $chatId = $payload['id'];
        $membership = $this->em->getRepository(ChatMember::class)
            ->findOneBy(['member' => $member]);
        self::assertNotNull($membership);
        self::assertSame($chatId, (string) $membership->getChat()->getId());
    }

    public function testCreateDmSucceeds(): void
    {
        $me   = $this->createUser('dmsender');
        $peer = $this->createUser('dmrecipient');
        $client = $this->createAuthenticatedClient($me);

        $client->jsonRequest('POST', '/api/chats', [
            'is_group'     => false,
            'participants' => ['dmrecipient'],
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($payload['is_group']);
        self::assertArrayHasKey('id', $payload);
    }

    public function testCreateDuplicateDmReturnsExisting(): void
    {
        $me   = $this->createUser('dmsender2');
        $peer = $this->createUser('dmrecipient2');
        $client = $this->createAuthenticatedClient($me);

        $client->jsonRequest('POST', '/api/chats', [
            'is_group'     => false,
            'participants' => ['dmrecipient2'],
        ]);
        $first = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $chatId = $first['id'];

        $client->jsonRequest('POST', '/api/chats', [
            'is_group'     => false,
            'participants' => ['dmrecipient2'],
        ]);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $second = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($second['already_exists']);
        self::assertSame($chatId, $second['id']);
    }

    public function testGetChatReturnsParticipants(): void
    {
        $owner  = $this->createUser('chatowner');
        $member = $this->createUser('chatmember');
        $chat   = $this->createGroupChat($owner, [$member]);
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', sprintf('/api/chats/%s', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame((string) $chat->getId(), $payload['id']);
        self::assertTrue($payload['is_group']);
        self::assertCount(2, $payload['participants']);

        $usernames = array_column($payload['participants'], 'username');
        self::assertContains('chatowner', $usernames);
        self::assertContains('chatmember', $usernames);
    }

    public function testGetForeignChatReturns403(): void
    {
        $owner    = $this->createUser('chatowner2');
        $outsider = $this->createUser('outsider2');
        $chat     = $this->createGroupChat($owner);
        $client   = $this->createAuthenticatedClient($outsider);

        $client->request('GET', sprintf('/api/chats/%s', $chat->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testOwnerCanDeleteGroupChat(): void
    {
        $owner  = $this->createUser('delowner');
        $member = $this->createUser('delmember');
        $chat   = $this->createGroupChat($owner, [$member]);
        $this->createMessage($chat, $owner, 'will be deleted');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('DELETE', sprintf('/api/chats/%s', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $deleted = $this->em->find(\App\Entity\Chat::class, (string) $chat->getId());
        self::assertNull($deleted);
    }

    public function testMemberCannotDeleteGroupChat(): void
    {
        $owner  = $this->createUser('owner3');
        $member = $this->createUser('member3');
        $chat   = $this->createGroupChat($owner, [$member]);
        $client = $this->createAuthenticatedClient($member);

        $client->request('DELETE', sprintf('/api/chats/%s', $chat->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }
}
