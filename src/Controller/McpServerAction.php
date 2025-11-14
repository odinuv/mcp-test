<?php

declare(strict_types=1);

namespace App\Controller;

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\HttpServerTransport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class McpServerAction
{
    #[Route('/mcp', name: 'mcp_server', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        // Create the MCP server
        $server = Server::make()
            ->withServerInfo('PHP Calculator Server', '1.0.0')
            ->build();

        // Discover tools in the src/Mcp directory
        $server->discover(
            basePath: dirname(__DIR__, 2),
            scanDirs: ['src/Mcp']
        );

        // Use HTTP transport
        $transport = new HttpServerTransport();

        // Start listening and return response
        ob_start();
        $server->listen($transport);
        $output = ob_get_clean();

        return new Response(
            $output,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }
}
