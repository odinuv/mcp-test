# MCP Server - Symfony Implementation

A Symfony-based MCP (Model Context Protocol) server that exposes various data access and utility tools via HTTP and Server-Sent Events (SSE) endpoints. Built with PHP 8.4 and Symfony 7.3.

## Features

- **MCP Protocol Support**: Implements the Model Context Protocol specification for AI assistant integration
- **Multiple Transport Layers**: Supports both StreamableHTTP and SSE transports
- **Extensible Tool System**: Easy-to-add tools using Symfony's service container
- **Data Access Tools**: Query meteorological data, sentiment data, and PostgreSQL databases
- **External API Integration**: Connect to Mapy.cz and other external services
- **Docker Development Environment**: Consistent development setup with RoadRunner server

## Available MCP Tools

- **PostgresMeteoTool**: Query meteorological station data from PostgreSQL
- **MeteoDataTool**: Access meteorological datasets
- **MeteoStationTool**: Retrieve weather station information
- **SentimentDataTool**: Access sentiment analysis datasets
- **ListDatasetsTool**: List available datasets
- **MapyPlacesTool**: Search places using Mapy.cz API
- **RecentTrafficTool**: Get recent traffic information
- **EchoTool**: Echo messages (utility/testing)
- **TimeTool**: Get current time in specified timezone (utility/testing)

## Quick Start

### Prerequisites

- Docker and Docker Compose
- (Optional) API keys for external services like Mapy.cz

### Installation
```shell
docker-compose run --rm dev composer install
```

### Configure Environment

Copy `.env` to `.env.local` and configure any necessary environment variables:

```bash
cp .env .env.local
```

Required environment variables for specific tools:
- `MAPY_API_KEY`: For MapyPlacesTool
- `POSTGRES_HOST`, `POSTGRES_PORT`, `POSTGRES_DATABASE`, `POSTGRES_USERNAME`, `POSTGRES_PASSWORD`: For PostgresMeteoTool

### Start the Server

```shell
docker-compose up dev-server
```

The server will be running on `localhost:8080`.

### Verify Installation

Check the health endpoint:
```shell
curl localhost:8080/health-check
```

List available MCP tools:
```shell
curl -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
  }'
```

## Using Docker
Project has Docker development environment setup, so you don't need to install anything on your local computer, except
the Docker & Docker Compose.

To run PHP scripts, use the `dev` service:
```shell
docker-compose run --rm dev composer install   # install dependencies using Composer 
docker-compose run --rm dev composer phpunit   # run Phpunit as a Composer script
docker-compose run --rm dev vendor/bin/phpunit # run Phpunit standalone
docker-compose run --rm dev bin/console        # run Symfony console commands
```

To run a webserver, hosting your app, use the `dev-server` service:
```shell
docker-compose up dev-server
```

To run all CI checks (validation, code style, static analysis, tests), use the `ci` service:
```shell
docker-compose run --rm ci
```

## MCP Endpoints

The server exposes the following MCP protocol endpoints:

### StreamableHTTP Transport
- `POST /mcp` - Direct HTTP request/response for MCP protocol messages

### SSE Transport
- `GET /mcp/sse` - Server-Sent Events endpoint for real-time streaming
- `POST /mcp/messages` - Submit messages to active SSE sessions

### Example: Call a Tool

```bash
curl -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
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

For more examples, see [MCP_TEST.md](MCP_TEST.md).

## Creating New Tools

1. Create a new class in `src/Mcp/` that implements `StreamableToolInterface`
2. Add the tool class to `config/packages/klp_mcp_server.yaml`
3. The tool will be automatically discovered and registered

See [MCP_TEST.md](MCP_TEST.md) for a complete example.

## Environment Configuration

For local development, the application follows Symfony best practices with `.env` files:
- `.env` - Versioned defaults for local development
- `.env.local` - Local overrides (not versioned)
- `.env.test` - Test environment configuration

Production deployments use regular environment variables (Docker `-e` flag) rather than `.env` files.

## Development

### Code Quality

```bash
# Run code style check
docker-compose run --rm dev composer phpcs

# Auto-fix code style issues
docker-compose run --rm dev composer phpcbf

# Run static analysis
docker-compose run --rm dev composer phpstan

# Run tests
docker-compose run --rm dev composer phpunit
```

### Running Single Tests

```bash
# Run specific test file
docker-compose run --rm dev vendor/bin/phpunit tests/Controller/IndexActionTest.php

# Run specific test method
docker-compose run --rm dev vendor/bin/phpunit --filter testActionReturnsResponse
```

## License

Proprietary

