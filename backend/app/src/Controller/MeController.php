<?php

namespace App\Controller;

use App\Entity\AvatarHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
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
        HubInterface $hub,
    ): JsonResponse {
        /** @var User $user */
        $data = json_decode($request->getContent(), true) ?? [];

        $avatarChanged = false;
        if (array_key_exists('avatar_url', $data)) {
            $newUrl = $data['avatar_url'] !== '' ? $data['avatar_url'] : null;
            if ($newUrl && $newUrl !== $user->getAvatarUrl()) {
                $exists = $em->getRepository(AvatarHistory::class)->findOneBy(['user' => $user, 'avatarUrl' => $newUrl]);
                if (!$exists) {
                    $h = new AvatarHistory();
                    $h->setUser($user);
                    $h->setAvatarUrl($newUrl);
                    $em->persist($h);
                }
            }
            $user->setAvatarUrl($newUrl);
            $avatarChanged = true;
        }

        $em->flush();

        if ($avatarChanged) {
            $userId = (string) $user->getId();
            $hub->publish(new Update(
                sprintf('/users/%s', $userId),
                json_encode([
                    'type' => 'user.updated',
                    'data' => ['user_id' => $userId, 'avatar_url' => $user->getAvatarUrl()],
                ], JSON_UNESCAPED_SLASHES),
                true
            ));
        }

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
