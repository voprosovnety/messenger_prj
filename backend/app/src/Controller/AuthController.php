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
        $name = $data['name'] ?? null;

        if (!$email || !$password || !$name) {
            return new JsonResponse(['error' => 'email, password, name are required'], 400);
        }

        // простая проверка на существование email
        $exists = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($exists) {
            return new JsonResponse(['error' => 'email already exists'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);

        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
        ], 201);
    }
}
