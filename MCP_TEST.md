# MCP Server Testing Guide

This document shows how to test the MCP server with HTTP streamable transport.

## Available Endpoints

- **StreamableHTTP**: `GET|POST /mcp` - Direct HTTP endpoint for request/response
- **SSE Streaming**: `GET /mcp/sse` - Server-Sent Events endpoint for real-time streaming
- **Message Submission**: `POST /mcp/messages` - Submit messages to SSE sessions

## Available Tools

### 1. Echo Tool
- **Name**: `echo`
- **Description**: Echoes back the provided message with an optional prefix
- **Arguments**:
  - `message` (string, required): The message to echo back
  - `prefix` (string, optional): Optional prefix to add before the message

### 2. Calculator Tool
- **Name**: `calculator`
- **Description**: Performs basic arithmetic operations on two numbers
- **Arguments**:
  - `operation` (string, required): One of: add, subtract, multiply, divide
  - `a` (number, required): First number
  - `b` (number, required): Second number

### 3. Time Tool
- **Name**: `get-time`
- **Description**: Gets the current date and time in specified timezone
- **Arguments**:
  - `timezone` (string, optional): Timezone identifier (default: "UTC")
  - `format` (string, optional): Date format (default: "Y-m-d H:i:s")

### 4. UUID Generator
- **Name**: `generate-uuid`
- **Description**: Generates random UUIDs (version 4)
- **Arguments**:
  - `count` (integer, optional): Number of UUIDs to generate, 1-10 (default: 1)

### 5. Weather Tool
- **Name**: `get-weather`
- **Description**: Gets simulated weather information for a city
- **Arguments**:
  - `city` (string, required): City name
  - `units` (string, optional): "celsius" or "fahrenheit" (default: "celsius")

## Starting the Server

```bash
# Using PHP built-in server
php -S localhost:8000 -t public

# Or using Symfony CLI
symfony serve
```

## Testing with cURL

### 1. Initialize Connection

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2024-11-05",
      "capabilities": {},
      "clientInfo": {
        "name": "test-client",
        "version": "1.0.0"
      }
    }
  }'
```

### 2. List Available Tools

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type": application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/list",
    "params": {}
  }'
```

### 3. Call the Echo Tool

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "echo",
      "arguments": {
        "message": "Hello MCP Server!",
        "prefix": "Test"
      }
    }
  }'
```

### 4. Call Calculator Tool (Add)

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "tools/call",
    "params": {
      "name": "calculator",
      "arguments": {
        "operation": "add",
        "a": 15,
        "b": 27
      }
    }
  }'
```

### 5. Generate UUIDs

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 5,
    "method": "tools/call",
    "params": {
      "name": "generate-uuid",
      "arguments": {
        "count": 3
      }
    }
  }'
```

### 6. Get Current Time

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 6,
    "method": "tools/call",
    "params": {
      "name": "get-time",
      "arguments": {
        "timezone": "America/New_York",
        "format": "Y-m-d H:i:s"
      }
    }
  }'
```

### 7. Get Weather Information

```bash
curl -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 7,
    "method": "tools/call",
    "params": {
      "name": "get-weather",
      "arguments": {
        "city": "New York",
        "units": "fahrenheit"
      }
    }
  }'
```

### 8. Using SSE (Server-Sent Events)

Open SSE connection:
```bash
curl -N http://localhost:8000/mcp/sse
```

In another terminal, send a message:
```bash
curl -X POST http://localhost:8000/mcp/messages \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
  }'
```

## Configuration

The MCP server configuration is in `config/packages/klp_mcp_server.yaml`:

```yaml
klp_mcp_server:
  enabled: true
  server:
    name: 'Simple MCP Server'
    version: '1.0.0'
  server_providers: ['streamable_http', 'sse']
  sse_adapter: 'cache'
  adapters:
    cache:
      prefix: 'mcp_sse_'
      ttl: 100
  tools:
    - App\Mcp\EchoTool
    - App\Mcp\CalculatorTool
    - App\Mcp\TimeTool
    - App\Mcp\UuidTool
    - App\Mcp\WeatherTool
```

## Creating New Tools

To create a new tool, add a class in `src/Mcp/` that implements `StreamableToolInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Mcp;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class MyTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'my-tool';
    }

    public function getDescription(): string
    {
        return 'Description of what this tool does';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'param',
                type: PropertyType::STRING,
                description: 'Parameter description',
                required: true
            )
        );
    }

    public function getOutputSchema(): ?StructuredSchema
    {
        return null;
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            title: 'My Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $param = $arguments['param'] ?? '';
        return new TextToolResult("Result: " . $param);
    }

    public function isStreaming(): bool
    {
        return false;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        // Only needed for streaming tools
    }
}
```

Then add your tool class to `config/packages/klp_mcp_server.yaml`:

```yaml
tools:
  - App\Mcp\MyTool
```

And make sure your tool services are configured in `config/services.yaml`:

```yaml
App\Mcp\:
    resource: '../src/Mcp/*Tool.php'
    public: true
    tags: ['klp_mcp_server.tool']
```

The tool will be automatically discovered and available via the MCP endpoints!

## Testing Tools

List all configured tools:
```bash
php bin/console mcp:test-tool --list
```

Note: The built-in test command may have issues with StructuredSchema. Use HTTP endpoints for testing instead.
