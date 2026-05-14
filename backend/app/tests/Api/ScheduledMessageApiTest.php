<?php

namespace App\Tests\Api;

use App\Entity\Message;
use App\Entity\ScheduledMessage;
use App\Service\ScheduledMessageDispatcher;

final class ScheduledMessageApiTest extends ApiTestCase
{
    public function testCreateListUpdateDelete(): void
    {
        $author = $this->createUser('author');
        $chat = $this->createGroupChat($author);

        $client = $this->createAuthenticatedClient($author);

        $when = (new \DateTimeImmutable('+1 hour'))->format(\DATE_ATOM);
        $client->jsonRequest('POST', sprintf('/api/chats/%s/scheduled-messages', $chat->getId()), [
            'content' => 'see you later',
            'scheduled_at' => $when,
        ]);
        self::assertSame(201, $client->getResponse()->getStatusCode());
        $created = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('see you later', $created['content']);
        self::assertArrayHasKey('id', $created);

        // List
        $client->request('GET', sprintf('/api/chats/%s/scheduled-messages', $chat->getId()));
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $list = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $list['items']);
        self::assertSame($created['id'], $list['items'][0]['id']);

        // Update content + scheduled_at
        $newWhen = (new \DateTimeImmutable('+2 hours'))->format(\DATE_ATOM);
        $client->jsonRequest('PATCH', sprintf('/api/scheduled-messages/%s', $created['id']), [
            'content' => 'updated text',
            'scheduled_at' => $newWhen,
        ]);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('updated text', $updated['content']);

        // Delete
        $client->request('DELETE', sprintf('/api/scheduled-messages/%s', $created['id']));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $this->em->clear();
        self::assertNull($this->em->getRepository(ScheduledMessage::class)->find($created['id']));
    }

    public function testForeignUserCannotEditOrDelete(): void
    {
        $author = $this->createUser('author');
        $stranger = $this->createUser('stranger');
        $chat = $this->createGroupChat($author);

        $client = $this->createAuthenticatedClient($author);
        $client->jsonRequest('POST', sprintf('/api/chats/%s/scheduled-messages', $chat->getId()), [
            'content' => 'private',
            'scheduled_at' => (new \DateTimeImmutable('+1 hour'))->format(\DATE_ATOM),
        ]);
        self::assertSame(201, $client->getResponse()->getStatusCode());
        $created = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client2 = $this->createAuthenticatedClient($stranger);
        $client2->jsonRequest('PATCH', sprintf('/api/scheduled-messages/%s', $created['id']), [
            'content' => 'hacked',
        ]);
        self::assertSame(403, $client2->getResponse()->getStatusCode());

        $client2->request('DELETE', sprintf('/api/scheduled-messages/%s', $created['id']));
        self::assertSame(403, $client2->getResponse()->getStatusCode());
    }

    public function testDispatcherTurnsDueScheduleIntoRealMessage(): void
    {
        $author = $this->createUser('author');
        $chat = $this->createGroupChat($author);

        $past = new \DateTimeImmutable('-1 minute');
        $sm = new ScheduledMessage($chat, $author, $past);
        $sm->setContent('delivered');
        $this->em->persist($sm);
        $this->em->flush();

        /** @var ScheduledMessageDispatcher $dispatcher */
        $dispatcher = static::getContainer()->get(ScheduledMessageDispatcher::class);
        $n = $dispatcher->dispatchDue();
        self::assertSame(1, $n);

        $this->em->clear();
        self::assertNull($this->em->getRepository(ScheduledMessage::class)->find((string) $sm->getId()));

        $messages = $this->em->getRepository(Message::class)->findBy(['chat' => $chat]);
        self::assertCount(1, $messages);
        self::assertSame('delivered', $messages[0]->getContent());
    }
}
