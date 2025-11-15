<?php

declare(strict_types=1);

namespace App\Mcp;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use PDO;
use PDOException;

class PostgresMeteoTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'get-meteostanice-nazev';
    }

    public function getDescription(): string
    {
        return 'Connects to PostgreSQL database and returns unique values from the "nazev" column in the meteostanice_mesta_Olomouc table';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema();
    }

    public function getOutputSchema(): ?StructuredSchema
    {
        return null;
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            title: 'PostgreSQL Meteostanice Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        // Read credentials from environment variables
        $host = $_ENV['POSTGRES_HOST'] ?? '';
        $port = (int)($_ENV['POSTGRES_PORT'] ?? 5432);
        $database = $_ENV['POSTGRES_DATABASE'] ?? '';
        $username = $_ENV['POSTGRES_USERNAME'] ?? '';
        $password = $_ENV['POSTGRES_PASSWORD'] ?? '';

        if (empty($host) || empty($database) || empty($username)) {
            return new TextToolResult('Error: Missing required environment variables (POSTGRES_HOST, POSTGRES_DATABASE, POSTGRES_USERNAME)');
        }

        try {
            // Create PDO connection string for PostgreSQL
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;options=\'--client_encoding=UTF8\'',
                $host,
                $port,
                $database
            );

            // Connect to PostgreSQL using PDO
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Query for unique values in the "nazev" column
            $sql = 'SELECT DISTINCT "NAZEV" FROM public."meteostanice_mesta_Olomouc" ORDER BY "NAZEV";';
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll();

            // Format the results
            if (empty($results)) {
                $output = 'No unique values found in the "nazev" column.';
            } else {
                $values = array_column($results, 'NAZEV');
                $output = sprintf(
                    "Found %d unique value(s) in the \"nazev\" column:\n\n%s",
                    count($values),
                    implode("\n", array_map(fn($v) => "- " . ($v ?? '(NULL)'), $values))
                );
            }

            // Close connection
            $pdo = null;

            return new TextToolResult($output);

        } catch (PDOException $e) {
            $errorMessage = sprintf(
                "Database Error: %s\n\nError Code: %s",
                $e->getMessage(),
                $e->getCode()
            );
            return new TextToolResult($errorMessage);
        } catch (\Exception $e) {
            $errorMessage = sprintf(
                "Unexpected Error: %s",
                $e->getMessage()
            );
            return new TextToolResult($errorMessage);
        }
    }

    public function isStreaming(): bool
    {
        return false;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        // This tool doesn't use streaming, so no progress notifier needed
    }
}
