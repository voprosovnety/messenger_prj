<?php

namespace App\Tests\Api;

final class PollApiTest extends ApiTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function testCreatePollReturnsStructuredResponse(): void
    {
        $owner = $this->createUser('pollowner');
        $chat  = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question'         => 'Best language?',
            'options'          => ['PHP', 'Python', 'Go'],
            'multiple_answers' => false,
            'anonymous'        => false,
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('poll', $body['type']);
        self::assertSame('Best language?', $body['content']);
        $poll = $body['poll'];
        self::assertSame('Best language?', $poll['question']);
        self::assertCount(3, $poll['options']);
        self::assertSame('PHP',    $poll['options'][0]['text']);
        self::assertSame('Python', $poll['options'][1]['text']);
        self::assertSame('Go',     $poll['options'][2]['text']);
        self::assertFalse($poll['multiple_answers']);
        self::assertFalse($poll['anonymous']);
        self::assertSame(0, $poll['total_votes']);
        self::assertSame([], $poll['my_votes']);
    }

    public function testValidationFailsWithNoQuestion(): void
    {
        $owner  = $this->createUser('pollval1');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => '',
            'options'  => ['A', 'B'],
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testValidationFailsWithOnlyOneOption(): void
    {
        $owner  = $this->createUser('pollval2');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'One?',
            'options'  => ['Only'],
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testNonMemberCannotCreatePoll(): void
    {
        $owner   = $this->createUser('pollowner2');
        $stranger = $this->createUser('stranger');
        $chat    = $this->createGroupChat($owner);
        $client  = $this->createAuthenticatedClient($stranger);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'Forbidden?',
            'options'  => ['Yes', 'No'],
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Voting — single-answer
    // -------------------------------------------------------------------------

    public function testSingleAnswerVoteIsRecorded(): void
    {
        $owner  = $this->createUser('voter1');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'A or B?',
            'options'  => ['A', 'B'],
        ]);
        $msg = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $msgId = $msg['id'];

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [0],
        ]);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $poll = $body['poll'];

        self::assertSame(1, $poll['total_votes']);
        self::assertSame([0], $poll['my_votes']);
        self::assertSame(1, $poll['options'][0]['votes']);
        self::assertSame(0, $poll['options'][1]['votes']);
    }

    public function testSingleAnswerVoteTogglesOff(): void
    {
        $owner  = $this->createUser('voter2');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'Toggle?',
            'options'  => ['Yes', 'No'],
        ]);
        $msgId = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $voteUrl = sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId);

        // First vote
        $client->jsonRequest('POST', $voteUrl, ['options' => [0]]);
        self::assertSame(1, json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll']['total_votes']);

        // Second vote on same option → toggled off
        $client->jsonRequest('POST', $voteUrl, ['options' => [0]]);
        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];

        self::assertSame(0, $poll['total_votes']);
        self::assertSame([], $poll['my_votes']);
    }

    public function testSingleAnswerVoteReplacesPreviousChoice(): void
    {
        $owner  = $this->createUser('voter3');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'A or B?',
            'options'  => ['A', 'B'],
        ]);
        $msgId  = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $voteUrl = sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId);

        $client->jsonRequest('POST', $voteUrl, ['options' => [0]]);
        $client->jsonRequest('POST', $voteUrl, ['options' => [1]]);

        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertSame(1, $poll['total_votes']);
        self::assertSame([1], $poll['my_votes']);
        self::assertSame(0, $poll['options'][0]['votes']);
        self::assertSame(1, $poll['options'][1]['votes']);
    }

    // -------------------------------------------------------------------------
    // Voting — multiple-answer
    // -------------------------------------------------------------------------

    public function testMultipleAnswerVoteRecordsAllSelected(): void
    {
        $owner  = $this->createUser('voter4');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question'         => 'Pick all?',
            'options'          => ['X', 'Y', 'Z'],
            'multiple_answers' => true,
        ]);
        $msgId   = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $voteUrl = sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId);

        $client->jsonRequest('POST', $voteUrl, ['options' => [0, 2]]);

        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertSame(2, $poll['total_votes']);
        self::assertContains(0, $poll['my_votes']);
        self::assertContains(2, $poll['my_votes']);
        self::assertSame(1, $poll['options'][0]['votes']);
        self::assertSame(0, $poll['options'][1]['votes']);
        self::assertSame(1, $poll['options'][2]['votes']);
    }

    public function testMultipleAnswerVoteTogglesSingleOption(): void
    {
        $owner  = $this->createUser('voter5');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question'         => 'Multi toggle?',
            'options'          => ['A', 'B'],
            'multiple_answers' => true,
        ]);
        $msgId   = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $voteUrl = sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId);

        $client->jsonRequest('POST', $voteUrl, ['options' => [0, 1]]);
        // Toggle off option 0 only
        $client->jsonRequest('POST', $voteUrl, ['options' => [0]]);

        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertSame(1, $poll['total_votes']);
        self::assertSame([1], $poll['my_votes']);
    }

    // -------------------------------------------------------------------------
    // Anonymous poll
    // -------------------------------------------------------------------------

    public function testAnonymousPollHidesVoterNames(): void
    {
        $owner  = $this->createUser('anonvoter');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question'  => 'Secret?',
            'options'   => ['Yes', 'No'],
            'anonymous' => true,
        ]);
        $msgId   = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $voteUrl = sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId);

        $client->jsonRequest('POST', $voteUrl, ['options' => [0]]);

        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertTrue($poll['anonymous']);
        self::assertSame(1, $poll['options'][0]['votes']);
        self::assertSame([], $poll['options'][0]['voters']);
    }

    public function testNonAnonymousPollShowsVoterNames(): void
    {
        $owner  = $this->createUser('pubvoter');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question'  => 'Public?',
            'options'   => ['Yes', 'No'],
            'anonymous' => false,
        ]);
        $msgId   = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $voteUrl = sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId);

        $client->jsonRequest('POST', $voteUrl, ['options' => [0]]);

        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        $voterUsernames = array_column($poll['options'][0]['voters'], 'username');
        self::assertContains('pubvoter', $voterUsernames);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function testNonMemberCannotVote(): void
    {
        $owner    = $this->createUser('pollgatekeeper');
        $stranger = $this->createUser('uninvited');
        $chat     = $this->createGroupChat($owner);
        $client   = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'In or out?',
            'options'  => ['In', 'Out'],
        ]);
        $msgId = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client2 = $this->createAuthenticatedClient($stranger);
        $client2->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [0],
        ]);

        self::assertSame(403, $client2->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Mercure events
    // -------------------------------------------------------------------------

    public function testCreatePollPublishesMercureEvent(): void
    {
        $owner  = $this->createUser('pollmercure');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'Event?',
            'options'  => ['Yes', 'No'],
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());
        self::assertSame(1, $this->hub->count());

        $payload = json_decode($this->hub->getMessages()[0]['object']->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('message.created', $payload['type']);
        self::assertSame('poll', $payload['data']['type']);
        self::assertArrayHasKey('poll', $payload['data']);
    }

    public function testVotePublishesPollVotedEvent(): void
    {
        $owner  = $this->createUser('votevent');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question' => 'Event vote?',
            'options'  => ['A', 'B'],
        ]);
        $msgId = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $this->hub->reset();

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [1],
        ]);

        self::assertSame(1, $this->hub->count());
        $payload = json_decode($this->hub->getMessages()[0]['object']->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('poll.voted', $payload['type']);
        self::assertSame($msgId, $payload['data']['message_id']);
        self::assertArrayHasKey('poll', $payload['data']);
    }
}
