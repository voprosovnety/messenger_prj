<?php

namespace App\Tests\Api;

final class AuthLoginApiTest extends ApiTestCase
{
    public function testLoginWithUsernameSucceeds(): void
    {
        $this->createHashedUser('loginuser', 'secret123');

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'identifier' => 'loginuser',
            'password'   => 'secret123',
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('access_token', $payload);
        self::assertArrayHasKey('refresh_token', $payload);
        self::assertSame('loginuser', $payload['username']);
    }

    public function testLoginWithEmailSucceeds(): void
    {
        $this->createHashedUser('emailuser', 'secret123', 'emailuser@example.test');

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'identifier' => 'emailuser@example.test',
            'password'   => 'secret123',
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('access_token', $payload);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->createHashedUser('wrongpwuser', 'correct_password');

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'identifier' => 'wrongpwuser',
            'password'   => 'wrong',
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginWithUnknownUserReturns401(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/login', [
            'identifier' => 'nobody',
            'password'   => 'whatever',
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
