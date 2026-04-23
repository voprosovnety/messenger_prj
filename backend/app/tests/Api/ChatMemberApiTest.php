<?php

namespace App\Tests\Api;

use App\Entity\ChatMember;
use App\Entity\User;

final class ChatMemberApiTest extends ApiTestCase
{
    public function testOwnerCanAddAndRemoveParticipant(): void
    {
        $owner = $this->createUser('owner');
        $target = $this->createUser('target');
        $chat = $this->createGroupChat($owner);

        $client = $this->createAuthenticatedClient($owner);
        $client->jsonRequest('POST', sprintf('/api/chats/%s/members', $chat->getId()), [
            'identifier' => $target->getUsername(),
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());

        $membership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $target,
        ]);
        self::assertNotNull($membership);

        $client->request('DELETE', sprintf('/api/chats/%s/members/%s', $chat->getId(), $target->getId()));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $removed = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $target,
        ]);
        self::assertNull($removed);
    }

    public function testNonOwnerCannotAddParticipant(): void
    {
        $owner = $this->createUser('owner');
        $member = $this->createUser('member');
        $target = $this->createUser('target');
        $chat = $this->createGroupChat($owner, [$member]);

        $client = $this->createAuthenticatedClient($member);
        $client->jsonRequest('POST', sprintf('/api/chats/%s/members', $chat->getId()), [
            'identifier' => $target->getUsername(),
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testNonOwnerCannotRemoveParticipant(): void
    {
        $owner = $this->createUser('owner');
        $member = $this->createUser('member');
        $target = $this->createUser('target');
        $chat = $this->createGroupChat($owner, [$member, $target]);

        $client = $this->createAuthenticatedClient($member);
        $client->request('DELETE', sprintf('/api/chats/%s/members/%s', $chat->getId(), $target->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());

        $stillExists = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $target,
        ]);
        self::assertNotNull($stillExists);
    }

    public function testNonMemberCannotSendMessageToForeignChat(): void
    {
        $owner = $this->createUser('owner');
        $outsider = $this->createUser('outsider');
        $chat = $this->createGroupChat($owner);

        $client = $this->createAuthenticatedClient($outsider);
        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages', $chat->getId()), [
            'content' => 'hello from outside',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }
}
