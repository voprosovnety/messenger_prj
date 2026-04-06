<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController
{
    #[Route('/api/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $username = $data['username'] ?? null;

        if (!$email || !$password || !$username) {
            return new JsonResponse(['error' => 'email, password, username are required'], 400);
        }

        // простая проверка на существование email
        $existsEmail = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existsEmail) {
            return new JsonResponse(['error' => 'email already exists'], 409);
        }

        $existsUsername = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existsUsername) {
            return new JsonResponse(['error' => 'username already exists'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);

        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
        ], 201);
    }
}
