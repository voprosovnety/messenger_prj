<?php

namespace App\Tests\Api;

final class MeApiTest extends ApiTestCase
{
    public function testGetMeReturnsCurrentUser(): void
    {
        $user   = $this->createUser('meuser', 'me@example.test');
        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/me');

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('meuser', $payload['username']);
        self::assertSame('me@example.test', $payload['email']);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('avatar_url', $payload);
        self::assertArrayHasKey('last_seen_at', $payload);
    }

    public function testGetMeWithoutAuthReturns401(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testPatchMeUpdatesUsername(): void
    {
        $user   = $this->createUser('oldname');
        $client = $this->createAuthenticatedClient($user);

        $client->jsonRequest('PATCH', '/api/me', ['username' => 'newname']);

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('newname', $payload['username']);

        $this->em->refresh($user);
        self::assertSame('newname', $user->getUsername());
    }

    public function testPatchMeDuplicateUsernameReturns409(): void
    {
        $this->createUser('takenname');
        $user   = $this->createUser('myname');
        $client = $this->createAuthenticatedClient($user);

        $client->jsonRequest('PATCH', '/api/me', ['username' => 'takenname']);

        self::assertSame(409, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('username already taken', $payload['error']);
    }

    public function testPingUpdatesLastSeenAt(): void
    {
        $user   = $this->createUser('pinguser');
        $client = $this->createAuthenticatedClient($user);

        self::assertNull($user->getLastSeenAt());

        $client->request('POST', '/api/me/ping');

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $this->em->refresh($user);
        self::assertNotNull($user->getLastSeenAt());
    }
}
