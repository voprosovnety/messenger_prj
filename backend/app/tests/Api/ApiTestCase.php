<?php

namespace App\Tests\Api;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Tests\Support\NullHub;
use App\Tests\Support\StringUuidType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected NullHub $hub;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        static::bootKernel();
        $this->hub = static::getContainer()->get(NullHub::class);
        $this->hub->reset();
        $this->client = new KernelBrowser(static::$kernel, [], new History(), new CookieJar());
        $this->client->disableReboot();

        $this->em = $this->service(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        $this->resetDatabase();

        // Doctrine's AbstractUidType uses BLOB on SQLite (no native GUID platform),
        // which breaks DQL parameter comparison. Override AFTER the connection is
        // initialized (ConnectionFactory::initializeTypes runs during resetDatabase).
        if (Type::hasType('uuid')) {
            Type::overrideType('uuid', StringUuidType::class);
        } else {
            Type::addType('uuid', StringUuidType::class);
        }
    }

    protected function createAuthenticatedClient(User $user): KernelBrowser
    {
        $jwtManager = $this->service(JWTTokenManagerInterface::class, 'lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        return $this->client;
    }

    protected function createUser(
        string $username,
        ?string $email = null,
        string $password = 'password123'
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email ?? sprintf('%s@example.test', $username));
        $user->setPassword($password);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createHashedUser(
        string $username,
        string $password = 'password123',
        ?string $email = null,
    ): User {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email'    => $email ?? sprintf('%s@example.test', $username),
            'password' => $password,
            'username' => $username,
        ]);
        $this->em->clear();

        return $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    protected function makeRefreshToken(
        User $user,
        ?\DateTimeImmutable $expiresAt = null,
        ?\DateTimeImmutable $revokedAt = null,
    ): RefreshToken {
        $token = new RefreshToken();
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setOwner($user);
        $token->setExpiresAt($expiresAt ?? new \DateTimeImmutable('+7 days'));
        if ($revokedAt !== null) {
            $token->setRevokedAt($revokedAt);
        }
        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    protected function createGroupChat(User $owner, array $members = [], string $title = 'Test Group'): Chat
    {
        $chat = new Chat();
        $chat->setIsGroup(true);
        $chat->setTitle($title);
        $this->em->persist($chat);

        $ownerMembership = new ChatMember();
        $ownerMembership->setChat($chat);
        $ownerMembership->setMember($owner);
        $ownerMembership->setRole('OWNER');
        $this->em->persist($ownerMembership);

        foreach ($members as $member) {
            $membership = new ChatMember();
            $membership->setChat($chat);
            $membership->setMember($member);
            $membership->setRole('MEMBER');
            $this->em->persist($membership);
        }

        $this->em->flush();

        return $chat;
    }

    protected function createMessage(Chat $chat, User $sender, string $content = 'hello'): Message
    {
        $message = new Message();
        $message->setChat($chat);
        $message->setSender($sender);
        $message->setContent($content);

        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    private function resetDatabase(): void
    {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);

        if ($metadata !== []) {
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }

        $this->em->clear();
    }

    private function service(string $id, ?string $fallbackId = null): mixed
    {
        $container = static::getContainer();
        foreach (array_filter([$id, $fallbackId]) as $candidate) {
            try {
                return $container->get($candidate);
            } catch (\Exception) {
                // try next candidate
            }
        }

        throw new \RuntimeException(sprintf('Service "%s" not found.', $id));
    }
}
