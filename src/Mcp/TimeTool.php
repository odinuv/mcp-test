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

class TimeTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'get-time';
    }

    public function getDescription(): string
    {
        return 'Gets the current date and time in the specified timezone and format';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'timezone',
                type: PropertyType::STRING,
                description: 'Timezone identifier (e.g., "UTC", "America/New_York", "Europe/London")',
                default: 'UTC',
                required: false
            ),
            new SchemaProperty(
                name: 'format',
                type: PropertyType::STRING,
                description: 'Date format (e.g., "Y-m-d H:i:s", "c", "r")',
                default: 'Y-m-d H:i:s',
                required: false
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
            title: 'Time Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: false,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $timezone = $arguments['timezone'] ?? 'UTC';
        $format = $arguments['format'] ?? 'Y-m-d H:i:s';

        try {
            $dateTime = new \DateTime('now', new \DateTimeZone($timezone));
            $formattedTime = $dateTime->format($format);

            return new TextToolResult(
                sprintf('Current time in %s: %s', $timezone, $formattedTime)
            );
        } catch (\Exception $e) {
            return new TextToolResult('Error: ' . $e->getMessage());
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
