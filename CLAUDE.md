# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 7.3 PHP application that serves as an MCP (Model Context Protocol) server. It exposes various tools via HTTP and Server-Sent Events (SSE) endpoints for integration with AI assistants and other clients. The application uses Docker for development and deployment, with RoadRunner as the PHP application server.

## Development Commands

### Using Docker (Recommended)

All development commands should be run through Docker Compose to ensure consistent environments:

```bash
# Install dependencies
docker-compose run --rm dev composer install

# Start development server (available at localhost:8080)
docker-compose up dev-server

# Run all CI checks (validation, phpcs, phpstan, phpunit)
docker-compose run --rm ci

# Run individual tools
docker-compose run --rm dev composer phpcs          # Code style check
docker-compose run --rm dev composer phpcbf         # Code style auto-fix
docker-compose run --rm dev composer phpstan        # Static analysis
docker-compose run --rm dev composer phpunit        # Run tests
docker-compose run --rm dev vendor/bin/phpunit      # Direct PHPUnit
docker-compose run --rm dev bin/console             # Symfony console
```

### Running Single Tests

```bash
# Run a specific test file
docker-compose run --rm dev vendor/bin/phpunit tests/Controller/IndexActionTest.php

# Run a specific test method
docker-compose run --rm dev vendor/bin/phpunit --filter testActionReturnsResponse
```

### Testing the MCP Server

```bash
# List available MCP tools
php bin/console mcp:test-tool --list

# Test via HTTP (from host, server must be running)
curl -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "id": 1, "method": "tools/list", "params": {}}'
```

## Architecture

### MCP Server Implementation

The application implements the Model Context Protocol (MCP) specification using the `klapaudius/symfony-mcp-server` package. The architecture is designed to expose PHP tools as MCP-compatible endpoints.

**Key Components:**

- **Transport Layers**: Supports both StreamableHTTP (`/mcp`) and SSE (`/mcp/sse`) transports
- **Tool Registry**: Tools are auto-discovered via Symfony service tags (`klp_mcp_server.tool`)
- **Tool Interface**: All tools implement `StreamableToolInterface` from the MCP server package

### Tool Development Pattern

Tools are located in `src/Mcp/` and follow this structure:

```php
namespace App\Mcp;

use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class MyTool implements StreamableToolInterface
{
    public function getName(): string { /* unique tool name */ }
    public function getDescription(): string { /* what the tool does */ }
    public function getInputSchema(): StructuredSchema { /* input parameters */ }
    public function getOutputSchema(): ?StructuredSchema { /* optional output schema */ }
    public function getAnnotations(): ToolAnnotation { /* tool metadata */ }
    public function execute(array $arguments): ToolResultInterface { /* business logic */ }
    public function isStreaming(): bool { /* whether tool streams results */ }
    public function setProgressNotifier(ProgressNotifierInterface $p): void { /* for streaming */ }
}
```

**Tool Registration Process:**

1. Create tool class in `src/Mcp/` with `*Tool.php` suffix
2. Tool is automatically registered via service configuration in `config/services.yaml`:
   ```yaml
   App\Mcp\:
       resource: '../src/Mcp/*Tool.php'
       public: true
       tags: ['klp_mcp_server.tool']
   ```
3. Add tool class to `config/packages/klp_mcp_server.yaml` tools list
4. Tool becomes available via MCP endpoints automatically

### Service Configuration

- **Service Autowiring**: Enabled by default for all services in `src/`
- **Controller Registration**: Controllers and Actions in `src/Controller/` with `*Controller` or `*Action` suffix are auto-registered
- **Environment Configuration**: Uses Symfony's `.env` pattern for local development, but Docker images use environment variables directly

### Current MCP Tools

The application includes several specialized tools:

- **Data Access Tools**: `PostgresMeteoTool`, `MeteoDataTool`, `MeteoStationTool`, `SentimentDataTool`, `ListDatasetsTool` - Query various data sources
- **External API Tools**: `MapyPlacesTool`, `RecentTrafficTool` - Integrate with external services (requires `MAPY_API_KEY` in environment)
- **Utility Tools**: `EchoTool`, `TimeTool` - Simple demonstration tools

**Database Tools**: Several tools connect to PostgreSQL using PDO. Connection details are read from environment variables: `POSTGRES_HOST`, `POSTGRES_PORT`, `POSTGRES_DATABASE`, `POSTGRES_USERNAME`, `POSTGRES_PASSWORD`.

### Application Structure

- `src/Controller/`: HTTP controllers for health checks and index
- `src/Mcp/`: MCP tool implementations
- `src/Roadrunner/`: RoadRunner-specific handlers (logging)
- `config/packages/`: Bundle configurations including `klp_mcp_server.yaml`
- `config/services.yaml`: Main service container configuration
- `public/`: Web root with index.php entry point
- `tests/`: PHPUnit tests organized by component

### PHP Standards

- **PHP Version**: 8.4 with strict types (`declare(strict_types=1);`)
- **Coding Standard**: Uses `keboola/coding-standard` for phpcs/phpcbf
- **Static Analysis**: PHPStan level configured in `phpstan-{env}.neon` files
- **Testing**: PHPUnit 12 with Symfony test utilities

## Environment Configuration

Development uses `.env` files (Symfony pattern):
- `.env` - Versioned defaults
- `.env.local` - Local overrides (not versioned)
- `.env.test` - Test environment settings

**Required Environment Variables:**

- `APP_NAME`: Application identifier (default: `symfony-skeleton`)
- `APP_VERSION`: Version string (default: `DEV`)
- `APP_SECRET`: Symfony secret key
- `MAPY_API_KEY`: API key for Mapy.cz integration (for MapyPlacesTool)
- `POSTGRES_*`: Database credentials for PostgreSQL tools

Production deployments receive configuration via container environment variables (not `.env` files).
