<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/health-check', name: 'app_healthCheck', methods: ['GET'])]
class HealthCheckAction
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}
