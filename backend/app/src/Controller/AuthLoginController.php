<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AuthLoginController
{
    #[Route('/api/auth/login', name: 'auth_login', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        // Если ты видишь этот ответ — значит security не перехватил запрос
        return new JsonResponse(['error' => 'login not handled by security'], 500);
    }
}
