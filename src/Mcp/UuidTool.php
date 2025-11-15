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

class UuidTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'generate-uuid';
    }

    public function getDescription(): string
    {
        return 'Generates a random UUID (version 4)';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'count',
                type: PropertyType::INTEGER,
                description: 'Number of UUIDs to generate (1-10)',
                default: '1',
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
            title: 'UUID Generator',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: false,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $count = (int) ($arguments['count'] ?? 1);
        $count = max(1, min(10, $count)); // Limit between 1 and 10

        $uuids = [];
        for ($i = 0; $i < $count; $i++) {
            $uuids[] = $this->generateUuid();
        }

        if ($count === 1) {
            return new TextToolResult($uuids[0]);
        }

        return new TextToolResult(
            "Generated {$count} UUIDs:\n" . implode("\n", $uuids)
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);

        // Set version to 0100 (UUID version 4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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
