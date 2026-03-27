<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiPingController
{
    #[Route('/api/ping', name: 'api_ping', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
