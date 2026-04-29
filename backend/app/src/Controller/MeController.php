<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class MeController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(?UserInterface $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        /** @var User $user */
        return new JsonResponse($this->serialize($user));
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function update(
        Request $request,
        UserInterface $user,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['username'])) {
            $username = trim((string) $data['username']);
            if ($username === '') {
                return new JsonResponse(['error' => 'username cannot be empty'], 400);
            }
            $existing = $em->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existing && (string) $existing->getId() !== (string) $user->getId()) {
                return new JsonResponse(['error' => 'username already taken'], 409);
            }
            $user->setUsername($username);
        }

        if (array_key_exists('avatar_url', $data)) {
            $user->setAvatarUrl($data['avatar_url'] !== '' ? $data['avatar_url'] : null);
        }

        $em->flush();

        return new JsonResponse($this->serialize($user));
    }

    #[Route('/api/me/ping', name: 'api_me_ping', methods: ['POST'])]
    public function ping(
        UserInterface $user,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user->setLastSeenAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function serialize(User $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatar_url' => $user->getAvatarUrl(),
            'last_seen_at' => $user->getLastSeenAt()?->format(DATE_ATOM),
            'roles' => $user->getRoles(),
        ];
    }
}
