<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/', name: 'app_index', methods: ['GET'])]
class IndexAction
{
    public function __construct(
        private readonly string $appName,
        private readonly string $appVersion,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'appName' => $this->appName,
            'appVersion' => $this->appVersion,
            'apiDocs' => $request->getSchemeAndHttpHost() . '/docs/swagger.yaml',
        ]);
    }
}
