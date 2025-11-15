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

class CalculatorTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'calculator';
    }

    public function getDescription(): string
    {
        return 'Performs basic arithmetic operations (add, subtract, multiply, divide) on two numbers';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'operation',
                type: PropertyType::STRING,
                description: 'The arithmetic operation to perform',
                enum: ['add', 'subtract', 'multiply', 'divide'],
                required: true
            ),
            new SchemaProperty(
                name: 'a',
                type: PropertyType::NUMBER,
                description: 'First number',
                required: true
            ),
            new SchemaProperty(
                name: 'b',
                type: PropertyType::NUMBER,
                description: 'Second number',
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
            title: 'Calculator',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $operation = $arguments['operation'] ?? '';
        $a = (float) ($arguments['a'] ?? 0);
        $b = (float) ($arguments['b'] ?? 0);

        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $this->divide($a, $b),
            default => throw new \InvalidArgumentException('Invalid operation: ' . $operation),
        };

        return new TextToolResult(
            sprintf('%s %s %s = %s', $a, $this->getOperationSymbol($operation), $b, $result)
        );
    }

    private function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new \InvalidArgumentException('Division by zero is not allowed');
        }

        return $a / $b;
    }

    private function getOperationSymbol(string $operation): string
    {
        return match ($operation) {
            'add' => '+',
            'subtract' => '-',
            'multiply' => '×',
            'divide' => '÷',
            default => '?',
        };
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
