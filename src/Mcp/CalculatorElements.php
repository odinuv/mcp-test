<?php

declare(strict_types=1);

namespace App\Mcp;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class CalculatorElements
{
    #[McpTool(name: 'add_numbers', description: 'Add two numbers together')]
    public function add(
        #[Schema(description: 'First number')]
        int $a,
        #[Schema(description: 'Second number')]
        int $b
    ): int {
        return $a + $b;
    }

    #[McpTool(name: 'subtract_numbers', description: 'Subtract second number from first')]
    public function subtract(
        #[Schema(description: 'First number')]
        int $a,
        #[Schema(description: 'Second number')]
        int $b
    ): int {
        return $a - $b;
    }

    #[McpTool(name: 'multiply_numbers', description: 'Multiply two numbers')]
    public function multiply(
        #[Schema(description: 'First number')]
        int $a,
        #[Schema(description: 'Second number')]
        int $b
    ): int {
        return $a * $b;
    }

    #[McpTool(name: 'calculate_power', description: 'Calculate base raised to the power of exponent')]
    public function power(
        #[Schema(type: 'number', minimum: 0, maximum: 1000, description: 'Base number')]
        float $base,
        #[Schema(type: 'integer', minimum: 0, maximum: 10, description: 'Exponent')]
        int $exponent
    ): float {
        return pow($base, $exponent);
    }
}
