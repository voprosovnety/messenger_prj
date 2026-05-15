<?php

namespace App\Tests\Api;

use App\Entity\Message;

final class SearchApiTest extends ApiTestCase
{
    // -------------------------------------------------------------------------
    // In-chat search
    // -------------------------------------------------------------------------

    public function testInChatSearchFindsMatchingMessages(): void
    {
        $owner  = $this->createUser('searcher1');
        $chat   = $this->createGroupChat($owner);
        $this->createMessage($chat, $owner, 'hello world');
        $this->createMessage($chat, $owner, 'another message');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', sprintf('/api/chats/%s/messages/search?q=world', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame('hello world', $body['items'][0]['content']);
    }

    public function testInChatSearchIsCaseInsensitive(): void
    {
        $owner  = $this->createUser('searcher2');
        $chat   = $this->createGroupChat($owner);
        $this->createMessage($chat, $owner, 'Hello World');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', sprintf('/api/chats/%s/messages/search?q=hello', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
    }

    public function testInChatSearchQueryTooShortReturnsEmpty(): void
    {
        $owner  = $this->createUser('searcher3');
        $chat   = $this->createGroupChat($owner);
        $this->createMessage($chat, $owner, 'hi');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', sprintf('/api/chats/%s/messages/search?q=h', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $body['items']);
    }

    public function testInChatSearchExcludesDeletedMessages(): void
    {
        $owner   = $this->createUser('searcher4');
        $chat    = $this->createGroupChat($owner);
        $message = $this->createMessage($chat, $owner, 'delete me');
        $client  = $this->createAuthenticatedClient($owner);

        // Soft-delete the message
        $client->request('DELETE', sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('GET', sprintf('/api/chats/%s/messages/search?q=delete', $chat->getId()));

        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $body['items']);
    }

    public function testInChatSearchNonMemberGetsForbidden(): void
    {
        $owner    = $this->createUser('searchowner');
        $stranger = $this->createUser('searchstranger');
        $chat     = $this->createGroupChat($owner);
        $this->createMessage($chat, $owner, 'secret');
        $client = $this->createAuthenticatedClient($stranger);

        $client->request('GET', sprintf('/api/chats/%s/messages/search?q=secret', $chat->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testInChatSearchOnlyReturnsResultsFromThatChat(): void
    {
        $owner   = $this->createUser('searcher5');
        $chat1   = $this->createGroupChat($owner);
        $chat2   = $this->createGroupChat($owner);
        $this->createMessage($chat1, $owner, 'needle in chat1');
        $this->createMessage($chat2, $owner, 'needle in chat2');
        $client  = $this->createAuthenticatedClient($owner);

        $client->request('GET', sprintf('/api/chats/%s/messages/search?q=needle', $chat1->getId()));

        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame('needle in chat1', $body['items'][0]['content']);
    }

    // -------------------------------------------------------------------------
    // Global search
    // -------------------------------------------------------------------------

    public function testGlobalSearchFindsAcrossMultipleChats(): void
    {
        $owner  = $this->createUser('gsearcher1');
        $chat1  = $this->createGroupChat($owner, [], 'Chat One');
        $chat2  = $this->createGroupChat($owner, [], 'Chat Two');
        $this->createMessage($chat1, $owner, 'unique needle here');
        $this->createMessage($chat2, $owner, 'unique needle there');
        $this->createMessage($chat1, $owner, 'unrelated content');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', '/api/messages/search?q=needle');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['items']);
    }

    public function testGlobalSearchDoesNotExposeOtherUsersChats(): void
    {
        $owner    = $this->createUser('gsearcher2');
        $other    = $this->createUser('gsother');
        $ownChat  = $this->createGroupChat($owner);
        $otherChat = $this->createGroupChat($other);
        $this->createMessage($ownChat,  $owner, 'my needle');
        $this->createMessage($otherChat, $other, 'their needle');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', '/api/messages/search?q=needle');

        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame('my needle', $body['items'][0]['content']);
    }

    public function testGlobalSearchQueryTooShortReturnsEmpty(): void
    {
        $owner  = $this->createUser('gsearcher3');
        $chat   = $this->createGroupChat($owner);
        $this->createMessage($chat, $owner, 'ab test');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', '/api/messages/search?q=a');

        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $body['items']);
    }

    public function testGlobalSearchExcludesDeletedMessages(): void
    {
        $owner   = $this->createUser('gsearcher4');
        $chat    = $this->createGroupChat($owner);
        $message = $this->createMessage($chat, $owner, 'gone needle');
        $client  = $this->createAuthenticatedClient($owner);

        $client->request('DELETE', sprintf('/api/chats/%s/messages/%s', $chat->getId(), $message->getId()));

        $client->request('GET', '/api/messages/search?q=needle');

        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $body['items']);
    }

    public function testGlobalSearchResultIncludesChatMetadata(): void
    {
        $owner  = $this->createUser('gsearcher5');
        $chat   = $this->createGroupChat($owner, [], 'My Group');
        $this->createMessage($chat, $owner, 'metadata check');
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', '/api/messages/search?q=metadata');

        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        $item = $body['items'][0];
        self::assertArrayHasKey('chat_id', $item);
        self::assertArrayHasKey('chat_display_name', $item);
        self::assertSame('My Group', $item['chat_display_name']);
        self::assertTrue($item['chat_is_group']);
    }
}
