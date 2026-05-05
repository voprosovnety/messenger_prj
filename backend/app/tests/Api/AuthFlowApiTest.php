<?php

namespace App\Tests\Api;

use App\Entity\User;

final class AuthFlowApiTest extends ApiTestCase
{
    public function testRegisterSuccess(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email'    => 'alice@example.test',
            'password' => 'secret123',
            'username' => 'alice',
        ]);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('alice@example.test', $payload['email']);
        self::assertSame('alice', $payload['username']);
        self::assertArrayHasKey('id', $payload);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($user);
    }

    public function testRegisterDuplicateEmailReturns409(): void
    {
        $this->createUser('existing', 'taken@example.test');

        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email'    => 'taken@example.test',
            'password' => 'secret123',
            'username' => 'newcomer',
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('email already exists', $payload['error']);
    }

    public function testRegisterDuplicateUsernameReturns409(): void
    {
        $this->createUser('taken_user', 'original@example.test');

        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email'    => 'other@example.test',
            'password' => 'secret123',
            'username' => 'taken_user',
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('username already exists', $payload['error']);
    }

    public function testRefreshReturnsNewAccessToken(): void
    {
        $user  = $this->createUser('refreshable');
        $token = $this->makeRefreshToken($user);

        $this->client->jsonRequest('POST', '/api/auth/refresh', [
            'refresh_token' => $token->getToken(),
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('access_token', $payload);
        self::assertNotEmpty($payload['access_token']);
    }

    public function testRefreshWithRevokedTokenReturns401(): void
    {
        $user  = $this->createUser('revoked_user');
        $token = $this->makeRefreshToken($user, revokedAt: new \DateTimeImmutable());

        $this->client->jsonRequest('POST', '/api/auth/refresh', [
            'refresh_token' => $token->getToken(),
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testRefreshWithExpiredTokenReturns401(): void
    {
        $user  = $this->createUser('expired_user');
        $token = $this->makeRefreshToken($user, expiresAt: new \DateTimeImmutable('-1 day'));

        $this->client->jsonRequest('POST', '/api/auth/refresh', [
            'refresh_token' => $token->getToken(),
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

}
