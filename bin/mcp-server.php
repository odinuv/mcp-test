#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;

// Create the MCP server
$server = Server::make()
    ->withServerInfo('PHP Calculator Server', '1.0.0')
    ->build();

// Discover tools in the src/Mcp directory
$server->discover(
    basePath: __DIR__ . '/../',
    scanDirs: ['src/Mcp']
);

// Listen on stdio transport
$transport = new StdioServerTransport();
$server->listen($transport);
