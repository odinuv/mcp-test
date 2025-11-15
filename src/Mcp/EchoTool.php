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

class EchoTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'echo';
    }

    public function getDescription(): string
    {
        return 'Echoes back the provided message with an optional prefix';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'message',
                type: PropertyType::STRING,
                description: 'The message to echo back',
                required: true
            ),
            new SchemaProperty(
                name: 'prefix',
                type: PropertyType::STRING,
                description: 'Optional prefix to add before the message',
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
            title: 'Echo Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $message = $arguments['message'] ?? '';
        $prefix = $arguments['prefix'] ?? null;

        if ($prefix !== null) {
            return new TextToolResult($prefix . ': ' . $message);
        }

        return new TextToolResult($message);
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
