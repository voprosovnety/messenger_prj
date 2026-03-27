<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthRefreshController
{
    #[Route('/api/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $refreshValue = $data['refresh_token'] ?? null;

        if (!$refreshValue) {
            return new JsonResponse(['error' => 'refresh_token is required'], 400);
        }

        /** @var RefreshToken|null $refresh */
        $refresh = $em->getRepository(RefreshToken::class)->findOneBy(['token' => $refreshValue]);

        if (!$refresh || $refresh->getRevokedAt() !== null) {
            return new JsonResponse(['error' => 'invalid refresh_token'], 401);
        }

        if ($refresh->getExpiresAt() <= new \DateTimeImmutable()) {
            return new JsonResponse(['error' => 'refresh_token expired'], 401);
        }

        return new JsonResponse([
            'access_token' => $jwtManager->create($refresh->getOwner()),
        ]);
    }
}
