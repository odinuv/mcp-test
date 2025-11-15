#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\HttpServerTransport;

// Create the MCP server
$server = Server::make()
    ->withServerInfo('PHP Calculator Server', '1.0.0')
    ->build();

// Discover tools in the src/Mcp directory
$server->discover(
    basePath: __DIR__ . '/../',
    scanDirs: ['src/Mcp']
);

// Create HTTP transport (standalone server on port 8080)
$transport = new HttpServerTransport(
    host: '127.0.0.1',
    port: 8080,
    mcpPathPrefix: 'mcp'
);

echo "Starting MCP server...\n";
$server->listen($transport);

// Keep the event loop running
\React\EventLoop\Loop::run();
