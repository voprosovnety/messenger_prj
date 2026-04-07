<?php

namespace App\Security;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class JwtLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $em,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        $accessToken = $this->jwtManager->create($user);

        $refreshValue = bin2hex(random_bytes(32)); // 64 chars

        $refresh = new RefreshToken();
        $refresh->setToken($refreshValue);
        $refresh->setOwner($user);
        $refresh->setExpiresAt(new \DateTimeImmutable('+7 days'));

        $this->em->persist($refresh);
        $this->em->flush();

        /** @var \App\Entity\User $user */
        return new JsonResponse([
            'access_token' => $accessToken,
            'refresh_token' => $refreshValue,
            'username' => $user->getUsername(),
        ]);
    }
}
