<?php

namespace App\Tests\Api;

use App\Entity\Message;

final class ChatMediaTest extends ApiTestCase
{
    public function testMemberCanFetchMedia(): void
    {
        $owner = $this->createUser('mediaowner');
        $chat  = $this->createGroupChat($owner);

        // Persist a message with new-style attachments array manually
        $message = new Message();
        $message->setChat($chat);
        $message->setSender($owner);
        $message->setContent('');
        $message->setAttachments([
            ['url' => '/uploads/img.jpg', 'type' => 'image', 'name' => 'img.jpg'],
        ]);
        $this->em->persist($message);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', sprintf('/api/chats/%s/media', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('items', $data);
        self::assertCount(1, $data['items']);

        $item = $data['items'][0];
        self::assertSame('/uploads/img.jpg', $item['url']);
        self::assertSame('image', $item['type']);
        self::assertSame('mediaowner', $item['sender']);
    }

    public function testUnauthenticatedCannotFetchMedia(): void
    {
        $owner = $this->createUser('unauthowner');
        $chat  = $this->createGroupChat($owner);

        // Do NOT call createAuthenticatedClient — send request without JWT
        $this->client->request('GET', sprintf('/api/chats/%s/media', $chat->getId()));

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testNonMemberCannotFetchMedia(): void
    {
        $owner     = $this->createUser('chatowner');
        $nonMember = $this->createUser('outsider');
        $chat      = $this->createGroupChat($owner);

        $client = $this->createAuthenticatedClient($nonMember);
        $client->request('GET', sprintf('/api/chats/%s/media', $chat->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testDeletedMessageAttachmentExcluded(): void
    {
        $owner = $this->createUser('delowner');
        $chat  = $this->createGroupChat($owner);

        $message = new Message();
        $message->setChat($chat);
        $message->setSender($owner);
        $message->setContent('');
        $message->setAttachments([
            ['url' => '/uploads/gone.jpg', 'type' => 'image', 'name' => 'gone.jpg'],
        ]);
        $message->setDeletedAt(new \DateTimeImmutable());
        $this->em->persist($message);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', sprintf('/api/chats/%s/media', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $data['items']);
    }

    public function testLegacyAttachmentUrlIncluded(): void
    {
        $owner = $this->createUser('legacyowner');
        $chat  = $this->createGroupChat($owner);

        $message = new Message();
        $message->setChat($chat);
        $message->setSender($owner);
        $message->setContent('');
        $message->setAttachmentUrl('/uploads/file.pdf');
        $message->setAttachmentType('document');
        $message->setAttachmentName('file.pdf');
        $this->em->persist($message);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', sprintf('/api/chats/%s/media', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $data['items']);

        $item = $data['items'][0];
        self::assertSame('/uploads/file.pdf', $item['url']);
        self::assertSame('document', $item['type']);
        self::assertSame('file.pdf', $item['name']);
        self::assertSame('legacyowner', $item['sender']);
    }

    public function testNotFoundChatReturns404(): void
    {
        $owner = $this->createUser('notfoundowner');

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', '/api/chats/00000000-0000-0000-0000-000000000000/media');

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testSystemMessageExcluded(): void
    {
        $owner = $this->createUser('sysexclude');
        $chat  = $this->createGroupChat($owner);

        $message = new Message();
        $message->setChat($chat);
        $message->setSender($owner);
        $message->setContent('system event');
        $message->setType('system');
        $message->setAttachmentUrl('/uploads/sys.jpg');
        $message->setAttachmentType('image');
        $message->setAttachmentName('sys.jpg');
        $this->em->persist($message);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', '/api/chats/' . $chat->getId() . '/media');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data['items']);
    }
}
