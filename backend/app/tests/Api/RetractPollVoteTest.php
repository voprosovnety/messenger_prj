<?php

namespace App\Tests\Api;

use App\Entity\Poll;
use App\Entity\PollVote;

final class RetractPollVoteTest extends ApiTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a poll message via the API, returning ['msgId' => ..., 'chatId' => ...].
     * The $client must already be authenticated as a member of $chat.
     */
    private function createPollViaApi(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        \App\Entity\Chat $chat,
        bool $allowRetraction = true,
        bool $multipleAnswers = false,
    ): string {
        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/poll', $chat->getId()), [
            'question'          => 'Retract me?',
            'options'           => ['Option A', 'Option B', 'Option C'],
            'multiple_answers'  => $multipleAnswers,
            'allow_retraction'  => $allowRetraction,
        ]);

        self::assertSame(201, $client->getResponse()->getStatusCode());

        return json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
    }

    private function retractUrl(string $chatId, string $msgId): string
    {
        return sprintf('/api/chats/%s/messages/%s/poll/vote', $chatId, $msgId);
    }

    // -------------------------------------------------------------------------
    // Happy path: user has voted, retract removes all votes
    // -------------------------------------------------------------------------

    public function testRetractVoteRemovesAllUserVotesAndReturnsEmptyMyVotes(): void
    {
        $owner  = $this->createUser('retract_owner');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: true, multipleAnswers: true);

        // Cast votes on two options first
        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [0, 1],
        ]);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertSame(2, $poll['total_votes']);
        self::assertCount(2, $poll['my_votes']);

        // Retract all votes
        $client->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('poll', $body);

        $poll = $body['poll'];
        self::assertSame([], $poll['my_votes'], 'my_votes must be empty after retraction');
        self::assertSame(0, $poll['total_votes'], 'total_votes must be 0 after retraction');
        self::assertTrue($poll['allow_retraction']);
    }

    // -------------------------------------------------------------------------
    // Idempotent: retract when user has no votes
    // -------------------------------------------------------------------------

    public function testRetractWithNoExistingVotesIsIdempotent(): void
    {
        $owner  = $this->createUser('retract_novote');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: true);

        // Retract without having voted at all
        $client->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertSame([], $poll['my_votes']);
        self::assertSame(0, $poll['total_votes']);
    }

    // -------------------------------------------------------------------------
    // Forbidden: allow_retraction = false
    // -------------------------------------------------------------------------

    public function testRetractForbiddenWhenAllowRetractionIsFalse(): void
    {
        $owner  = $this->createUser('retract_noflag');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: false);

        // Vote first so there is something to retract
        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [0],
        ]);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        // Attempt retraction — must be blocked
        $client->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));

        self::assertSame(403, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $body);
    }

    // -------------------------------------------------------------------------
    // Forbidden: non-member
    // -------------------------------------------------------------------------

    public function testNonMemberCannotRetract(): void
    {
        $owner    = $this->createUser('retract_owner2');
        $stranger = $this->createUser('retract_stranger');
        $chat     = $this->createGroupChat($owner);

        // Owner creates the poll
        $ownerClient = $this->createAuthenticatedClient($owner);
        $msgId = $this->createPollViaApi($ownerClient, $chat, allowRetraction: true);

        // Stranger tries to retract (never a member of the chat)
        $strangerClient = $this->createAuthenticatedClient($stranger);
        $strangerClient->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));

        self::assertSame(403, $strangerClient->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Unauthenticated
    // -------------------------------------------------------------------------

    public function testUnauthenticatedCannotRetract(): void
    {
        $owner  = $this->createUser('retract_unauth');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: true);

        // Make a fresh client with no Authorization header
        $anonClient = new \Symfony\Bundle\FrameworkBundle\KernelBrowser(
            static::$kernel,
            [],
            new \Symfony\Component\BrowserKit\History(),
            new \Symfony\Component\BrowserKit\CookieJar()
        );
        $anonClient->disableReboot();
        $anonClient->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));

        self::assertSame(401, $anonClient->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Not found: non-existent chat
    // -------------------------------------------------------------------------

    public function testRetractReturns404ForUnknownChat(): void
    {
        $owner  = $this->createUser('retract_404chat');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: true);

        $fakeChat = '00000000-0000-0000-0000-000000000000';
        $client->request('DELETE', $this->retractUrl($fakeChat, $msgId));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Not found: non-existent message
    // -------------------------------------------------------------------------

    public function testRetractReturns404ForUnknownMessage(): void
    {
        $owner  = $this->createUser('retract_404msg');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $fakeMsg = '00000000-0000-0000-0000-000000000000';
        $client->request('DELETE', $this->retractUrl((string) $chat->getId(), $fakeMsg));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Mercure event: retract publishes poll.voted event
    // -------------------------------------------------------------------------

    public function testRetractPublishesPollVotedMercureEvent(): void
    {
        $owner  = $this->createUser('retract_mercure');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: true);

        // Vote
        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [0],
        ]);

        $this->hub->reset();

        // Retract
        $client->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        self::assertSame(1, $this->hub->count(), 'Exactly one Mercure event should be published on retraction');
        $payload = json_decode($this->hub->getMessages()[0]['object']->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('poll.voted', $payload['type']);
        self::assertSame($msgId, $payload['data']['message_id']);
        self::assertArrayHasKey('poll', $payload['data']);
    }

    // -------------------------------------------------------------------------
    // Edge case: single-choice poll — retract works the same
    // -------------------------------------------------------------------------

    public function testRetractSingleChoicePoll(): void
    {
        $owner  = $this->createUser('retract_single');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $msgId = $this->createPollViaApi($client, $chat, allowRetraction: true, multipleAnswers: false);

        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [2],
        ]);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('DELETE', $this->retractUrl((string) $chat->getId(), $msgId));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $poll = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        self::assertSame([], $poll['my_votes']);
        self::assertSame(0, $poll['total_votes']);
    }

    // -------------------------------------------------------------------------
    // Edge case: other users' votes are untouched after retraction
    // -------------------------------------------------------------------------

    public function testRetractOnlyRemovesCurrentUserVotes(): void
    {
        $owner  = $this->createUser('retract_owner3');
        $voter2 = $this->createUser('retract_voter2');
        $chat   = $this->createGroupChat($owner, [$voter2]);

        // Step 1: owner creates the poll
        $client = $this->createAuthenticatedClient($owner);
        $msgId  = $this->createPollViaApi($client, $chat, allowRetraction: true);

        // Step 2: owner votes on option 0
        $client->jsonRequest('POST', sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId), [
            'options' => [0],
        ]);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        // Step 3: voter2 votes on option 1 (switch auth header to voter2)
        $this->createAuthenticatedClient($voter2)->jsonRequest(
            'POST',
            sprintf('/api/chats/%s/messages/%s/poll/vote', $chat->getId(), $msgId),
            ['options' => [1]]
        );
        self::assertSame(200, $client->getResponse()->getStatusCode());

        // Step 4: switch back to owner and retract
        $retractClient = $this->createAuthenticatedClient($owner);
        $retractClient->request(
            'DELETE',
            $this->retractUrl((string) $chat->getId(), $msgId)
        );
        self::assertSame(200, $retractClient->getResponse()->getStatusCode());

        $poll = json_decode($retractClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['poll'];
        // Owner has no votes; voter2's vote on option 1 must remain
        self::assertSame([], $poll['my_votes'], 'Owner my_votes should be empty');
        self::assertSame(1, $poll['total_votes'], 'voter2\'s vote must remain');
        self::assertSame(0, $poll['options'][0]['votes'], 'Option 0 vote removed');
        self::assertSame(1, $poll['options'][1]['votes'], 'Option 1 vote kept');
    }
}
