<?php

namespace App\Tests\Api;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\User;
use App\Tests\Support\NullHub;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\Mercure\HubInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        static::bootKernel();
        $nullHub = new NullHub();
        $this->forcePrivateService('mercure.hub.default.traceable', $nullHub);
        $this->forcePrivateService('mercure.hub.default.traceable.inner', $nullHub);
        $this->forcePrivateService(HubInterface::class, $nullHub);
        $this->client = new KernelBrowser(static::$kernel, [], new History(), new CookieJar());

        $this->em = $this->service(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        $this->resetDatabase();
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

    private function forcePrivateService(string $id, object $service): void
    {
        $container = static::$kernel->getContainer();
        $reflection = new \ReflectionObject($container);

        while ($reflection) {
            if ($reflection->hasProperty('privates')) {
                $property = $reflection->getProperty('privates');
                $privates = $property->getValue($container);
                $privates[$id] = $service;
                $property->setValue($container, $privates);
                return;
            }

            $reflection = $reflection->getParentClass();
        }
    }

    private function service(string $id, ?string $fallbackId = null): mixed
    {
        $container = static::$kernel->getContainer();
        foreach (array_filter([$id, $fallbackId]) as $candidate) {
            if ($container->has($candidate)) {
                return $container->get($candidate);
            }
        }

        $reflection = new \ReflectionObject($container);
        while ($reflection) {
            if ($reflection->hasProperty('privates')) {
                $property = $reflection->getProperty('privates');
                $privates = $property->getValue($container);
                foreach (array_filter([$id, $fallbackId]) as $candidate) {
                    if (isset($privates[$candidate])) {
                        return $privates[$candidate];
                    }
                }
            }

            $reflection = $reflection->getParentClass();
        }

        throw new \RuntimeException(sprintf('Service "%s" not found.', $id));
    }
}
