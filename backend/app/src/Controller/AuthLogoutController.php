<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthLogoutController
{
    #[Route('/api/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshValue = $data['refresh_token'] ?? null;

        if (!$refreshValue) {
            return new JsonResponse(['error' => 'refresh_token is required'], 400);
        }

        $refresh = $em->getRepository(RefreshToken::class)->findOneBy(['token' => $refreshValue]);
        if ($refresh && $refresh->getRevokedAt() === null) {
            $refresh->setRevokedAt(new \DateTimeImmutable());
            $em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }
}
