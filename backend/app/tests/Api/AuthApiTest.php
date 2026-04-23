<?php

namespace App\Tests\Api;

final class AuthApiTest extends ApiTestCase
{
    public function testProtectedEndpointRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/chats');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
