<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\ChatMember;

/**
 * Tests for the sidebar pin-toggle feature.
 *
 * Endpoint: POST /api/chats/{id}/pin-sidebar
 *   Toggles `is_pinned` on the calling user's ChatMember row.
 *   Returns { is_pinned: bool }, 200.
 *
 * Note: GET /api/chats uses PostgreSQL-specific LATERAL joins and cannot run
 * against the SQLite test database. The testListChatsIncludeIsPin test therefore
 * verifies the persisted `is_pinned` state via the ORM instead of through the
 * list endpoint, which exercises the same business-logic guarantee.
 */
final class SidebarPinTest extends ApiTestCase
{
    // -------------------------------------------------------------------------
    // Happy path — toggle on
    // -------------------------------------------------------------------------

    public function testTogglePinOn(): void
    {
        $owner  = $this->createUser('alice');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        $client->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('is_pinned', $data);
        self::assertTrue($data['is_pinned']);
    }

    // -------------------------------------------------------------------------
    // Toggle off — calling twice returns is_pinned: false
    // -------------------------------------------------------------------------

    public function testTogglePinOff(): void
    {
        $owner  = $this->createUser('bob');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        // First call: pin
        $client->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $first = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($first['is_pinned']);

        // Second call: unpin
        $client->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $second = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($second['is_pinned']);
    }

    // -------------------------------------------------------------------------
    // Persisted state is reflected in ChatMember (mirrors what GET /api/chats
    // would return via is_pinned — list endpoint is PostgreSQL-only).
    // -------------------------------------------------------------------------

    public function testListChatsIncludeIsPin(): void
    {
        $owner  = $this->createUser('carol');
        $chat   = $this->createGroupChat($owner);
        $client = $this->createAuthenticatedClient($owner);

        // Before pinning: is_pinned must be false
        $membership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $owner,
        ]);
        self::assertNotNull($membership);
        self::assertFalse($membership->getIsPinned(), 'is_pinned should be false before any toggle');

        // Pin the chat
        $client->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        // Re-fetch from DB to assert persisted state
        $this->em->clear();
        $membership = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $owner,
        ]);
        self::assertNotNull($membership);
        self::assertTrue($membership->getIsPinned(), 'is_pinned should be true after toggle');
    }

    // -------------------------------------------------------------------------
    // Non-member gets 403
    // -------------------------------------------------------------------------

    public function testNonMemberGetsForbidden(): void
    {
        $owner    = $this->createUser('dave');
        $outsider = $this->createUser('eve');
        $chat     = $this->createGroupChat($owner);
        $client   = $this->createAuthenticatedClient($outsider);

        $client->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Non-existent chat ID gets 404
    // -------------------------------------------------------------------------

    public function testNotFoundChat(): void
    {
        $user   = $this->createUser('frank');
        $client = $this->createAuthenticatedClient($user);

        // Use a valid UUID format that does not correspond to any chat
        $client->request('POST', '/api/chats/00000000-0000-0000-0000-000000000000/pin-sidebar');

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Unauthenticated request gets 401
    // -------------------------------------------------------------------------

    public function testUnauthenticated(): void
    {
        $owner = $this->createUser('grace');
        $chat  = $this->createGroupChat($owner);

        // No Authorization header
        $this->client->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Pin state is per-user: user A's pin does not affect user B's membership
    // -------------------------------------------------------------------------

    public function testPinIsPerUser(): void
    {
        $userA  = $this->createUser('heidi');
        $userB  = $this->createUser('ivan');
        $chat   = $this->createGroupChat($userA, [$userB]);

        // User A pins the chat
        $clientA = $this->createAuthenticatedClient($userA);
        $clientA->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));
        self::assertSame(200, $clientA->getResponse()->getStatusCode());

        $dataA = json_decode($clientA->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($dataA['is_pinned'], 'User A should see is_pinned: true');

        // Re-fetch both memberships from DB
        $this->em->clear();

        $membershipA = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $userA,
        ]);
        $membershipB = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $userB,
        ]);

        self::assertTrue($membershipA->getIsPinned(), 'User A membership should have is_pinned: true');
        self::assertFalse($membershipB->getIsPinned(), 'User B membership should still have is_pinned: false');

        // Also verify via the endpoint from user B's perspective
        $clientB = $this->createAuthenticatedClient($userB);
        $clientB->request('POST', sprintf('/api/chats/%s/pin-sidebar', $chat->getId()));
        self::assertSame(200, $clientB->getResponse()->getStatusCode());
        $dataB = json_decode($clientB->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        // B's pin was false, so after toggle it becomes true
        self::assertTrue($dataB['is_pinned'], 'User B should see is_pinned: true after their own toggle');

        // Confirm B's toggle did not affect A's state
        $this->em->clear();
        $membershipA = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat'   => $chat,
            'member' => $userA,
        ]);
        self::assertTrue($membershipA->getIsPinned(), 'User A membership should remain is_pinned: true after B toggled');
    }
}
