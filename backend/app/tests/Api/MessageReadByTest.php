<?php

namespace App\Tests\Api;

use App\Entity\ChatMember;
use Symfony\Component\Uid\Uuid;

final class MessageReadByTest extends ApiTestCase
{
    private function url(string $chatId, string $messageId): string
    {
        return sprintf('/api/chats/%s/messages/%s/read-by', $chatId, $messageId);
    }

    public function testUnauthenticated(): void
    {
        $owner = $this->createUser('owner');
        $chat = $this->createGroupChat($owner);
        $message = $this->createMessage($chat, $owner, 'hello');

        $this->client->request('GET', $this->url((string) $chat->getId(), (string) $message->getId()));

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testNonMemberGetsForbidden(): void
    {
        $owner = $this->createUser('owner');
        $outsider = $this->createUser('outsider');
        $chat = $this->createGroupChat($owner);
        $message = $this->createMessage($chat, $owner, 'hello');

        $client = $this->createAuthenticatedClient($outsider);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $message->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testUnknownChatReturnsNotFound(): void
    {
        $owner = $this->createUser('owner');
        $chat = $this->createGroupChat($owner);
        $message = $this->createMessage($chat, $owner, 'hello');

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) Uuid::v4(), (string) $message->getId()));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testUnknownMessageReturnsNotFound(): void
    {
        $owner = $this->createUser('owner');
        $chat = $this->createGroupChat($owner);

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) Uuid::v4()));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testMessageFromDifferentChatReturnsNotFound(): void
    {
        $owner = $this->createUser('owner');
        $chat = $this->createGroupChat($owner, [], 'Chat A');
        $otherChat = $this->createGroupChat($owner, [], 'Chat B');
        $messageInOtherChat = $this->createMessage($otherChat, $owner, 'in other chat');

        $client = $this->createAuthenticatedClient($owner);
        // Request message from otherChat but using chat's ID
        $client->request('GET', $this->url((string) $chat->getId(), (string) $messageInOtherChat->getId()));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testNoReadersReturnsEmptyArray(): void
    {
        $owner = $this->createUser('owner');
        $member = $this->createUser('member');
        $chat = $this->createGroupChat($owner, [$member]);
        $message = $this->createMessage($chat, $owner, 'hello');

        // Neither owner nor member has any lastReadMessageId set

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $message->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testMemberWhoReadExactMessageIsIncluded(): void
    {
        $owner = $this->createUser('owner');
        $member = $this->createUser('reader');
        $chat = $this->createGroupChat($owner, [$member]);
        $message = $this->createMessage($chat, $owner, 'target');

        // Set member's lastReadMessageId to the exact target message
        $membership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $member,
        ]);
        $membership->setLastReadMessageId($message->getId());
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $message->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data);
        self::assertSame('reader', $data[0]['username']);
        self::assertArrayHasKey('avatar_url', $data[0]);
        self::assertArrayHasKey('read_at', $data[0]);
    }

    public function testMemberWhoReadLaterMessageIsIncluded(): void
    {
        $owner = $this->createUser('owner');
        $member = $this->createUser('reader');
        $chat = $this->createGroupChat($owner, [$member]);
        $targetMessage = $this->createMessage($chat, $owner, 'target');

        // Give the later message a distinctly later timestamp by sleeping briefly
        // Actually, SQLite stores microseconds so we need to ensure ordering.
        // We use usleep to guarantee the second message gets a later createdAt.
        usleep(2000);
        $laterMessage = $this->createMessage($chat, $owner, 'later');

        // Set member's lastReadMessageId to the later message
        $membership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $member,
        ]);
        $membership->setLastReadMessageId($laterMessage->getId());
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $targetMessage->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data);
        self::assertSame('reader', $data[0]['username']);
    }

    public function testMemberWhoReadEarlierMessageIsExcluded(): void
    {
        $owner = $this->createUser('owner');
        $member = $this->createUser('slowreader');
        $chat = $this->createGroupChat($owner, [$member]);
        $earlierMessage = $this->createMessage($chat, $owner, 'earlier');
        usleep(2000);
        $targetMessage = $this->createMessage($chat, $owner, 'target');

        // Set member's lastReadMessageId to the earlier message only
        $membership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $member,
        ]);
        $membership->setLastReadMessageId($earlierMessage->getId());
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $targetMessage->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testCallerExcludedFromResult(): void
    {
        $owner = $this->createUser('owner');
        $chat = $this->createGroupChat($owner);
        $message = $this->createMessage($chat, $owner, 'hello');

        // Set owner's own lastReadMessageId to the target message
        $ownerMembership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $owner,
        ]);
        $ownerMembership->setLastReadMessageId($message->getId());
        $this->em->flush();

        // Owner requests the endpoint — they should not appear in result
        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $message->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testMultipleMembersPartialRead(): void
    {
        $owner = $this->createUser('owner');
        $readerA = $this->createUser('readerA');
        $readerB = $this->createUser('readerB');
        $nonReader = $this->createUser('nonreader');
        $chat = $this->createGroupChat($owner, [$readerA, $readerB, $nonReader]);

        $targetMessage = $this->createMessage($chat, $owner, 'target');
        usleep(2000);
        $laterMessage = $this->createMessage($chat, $owner, 'later');

        // readerA: read exactly the target message
        $membershipA = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $readerA,
        ]);
        $membershipA->setLastReadMessageId($targetMessage->getId());

        // readerB: read a later message (should still be counted as read)
        $membershipB = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $readerB,
        ]);
        $membershipB->setLastReadMessageId($laterMessage->getId());

        // nonReader: lastReadMessageId stays null

        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', $this->url((string) $chat->getId(), (string) $targetMessage->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertCount(2, $data);
        $usernames = array_column($data, 'username');
        self::assertContains('readerA', $usernames);
        self::assertContains('readerB', $usernames);
        self::assertNotContains('nonreader', $usernames);
        self::assertNotContains('owner', $usernames);
    }
}
