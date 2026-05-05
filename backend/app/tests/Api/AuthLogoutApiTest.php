<?php

namespace App\Tests\Api;

final class AuthLogoutApiTest extends ApiTestCase
{
    public function testLogoutRevokesRefreshToken(): void
    {
        $user  = $this->createUser('logoutuser');
        $token = $this->makeRefreshToken($user);

        $this->client->jsonRequest('POST', '/api/auth/logout', [
            'refresh_token' => $token->getToken(),
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);

        $this->em->refresh($token);
        self::assertNotNull($token->getRevokedAt());
    }

    public function testLogoutAlreadyRevokedTokenIsIdempotent(): void
    {
        $user  = $this->createUser('logoutuser2');
        $token = $this->makeRefreshToken($user, revokedAt: new \DateTimeImmutable());

        $this->client->jsonRequest('POST', '/api/auth/logout', [
            'refresh_token' => $token->getToken(),
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testLogoutWithoutTokenReturns400(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/logout', []);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }
}
