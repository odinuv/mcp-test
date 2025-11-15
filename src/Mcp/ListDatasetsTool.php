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
use PDO;
use PDOException;

class ListDatasetsTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'list_olomouc_datasets';
    }

    public function getDescription(): string
    {
        return 'Retrieves a list of open datasets from the Olomouc Region (Olomoucký kraj) database, sorted alphabetically by title. Each dataset includes comprehensive metadata such as title, type, description, URL, owner, source, categories, tags, access information, field definitions, and sample data. This tool is useful for discovering available datasets, browsing data resources, and understanding what information is publicly available for the Olomouc region.';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of datasets to return. Must be a positive integer. Default is 50.',
                default: '50',
                required: false,
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
            title: 'Olomouc Datasets List Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        // Read credentials from environment variables using getenv()
        $host = getenv('POSTGRES_HOST') ?: '';
        $port = (int)(getenv('POSTGRES_PORT') ?: 5432);
        $database = getenv('POSTGRES_DATABASE') ?: '';
        $username = getenv('POSTGRES_USERNAME') ?: '';
        $password = getenv('POSTGRES_PASSWORD') ?: '';

        if (empty($host) || empty($database) || empty($username)) {
            return new TextToolResult('Error: Missing required environment variables (POSTGRES_HOST, POSTGRES_DATABASE, POSTGRES_USERNAME)');
        }

        // Parse and validate arguments
        $limit = max((int)($arguments['limit'] ?? 50), 1);

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

            // Query to get datasets sorted alphabetically by title
            $sql = '
                SELECT *
                FROM public."collections-cleaned"
                ORDER BY "title" ASC
                LIMIT :limit
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll();

            // Format the results
            if (empty($results)) {
                $output = 'No datasets found in the collections-cleaned table.';
            } else {
                $output = sprintf("Found %d dataset(s) from Olomouc Region:\n\n", count($results));

                foreach ($results as $index => $row) {
                    $output .= sprintf("Dataset #%d:\n", $index + 1);

                    // Add all available fields
                    if (!empty($row['title'])) {
                        $output .= sprintf("  Title: %s\n", $row['title']);
                    }
                    if (!empty($row['type'])) {
                        $output .= sprintf("  Type: %s\n", $row['type']);
                    }
                    if (!empty($row['description'])) {
                        $output .= sprintf("  Description: %s\n", $row['description']);
                    }
                    if (!empty($row['url'])) {
                        $output .= sprintf("  URL: %s\n", $row['url']);
                    }
                    if (!empty($row['owner'])) {
                        $output .= sprintf("  Owner: %s\n", $row['owner']);
                    }
                    if (!empty($row['source'])) {
                        $output .= sprintf("  Source: %s\n", $row['source']);
                    }
                    if (!empty($row['categories'])) {
                        $output .= sprintf("  Categories: %s\n", $row['categories']);
                    }
                    if (!empty($row['tags'])) {
                        $output .= sprintf("  Tags: %s\n", $row['tags']);
                    }
                    if (!empty($row['access'])) {
                        $output .= sprintf("  Access: %s\n", $row['access']);
                    }
                    if (!empty($row['fields'])) {
                        $output .= sprintf("  Field Definitions: %s\n", $row['fields']);
                    }
                    if (!empty($row['sample_data'])) {
                        $output .= sprintf("  Sample Data: %s\n", $row['sample_data']);
                    }

                    $output .= "\n";
                }
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
