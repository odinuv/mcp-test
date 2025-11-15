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

class WeatherTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'get-weather';
    }

    public function getDescription(): string
    {
        return 'Gets simulated weather information for a given city';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'city',
                type: PropertyType::STRING,
                description: 'City name',
                required: true
            ),
            new SchemaProperty(
                name: 'units',
                type: PropertyType::STRING,
                description: 'Temperature units',
                enum: ['celsius', 'fahrenheit'],
                default: 'celsius',
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
            title: 'Weather Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: false,
            openWorldHint: true
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $city = $arguments['city'] ?? 'Unknown';
        $units = $arguments['units'] ?? 'celsius';

        // Simulate weather data
        $conditions = ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy', 'Stormy'];
        $condition = $conditions[array_rand($conditions)];

        $baseTemp = rand(15, 30);
        if ($units === 'fahrenheit') {
            $temperature = ($baseTemp * 9/5) + 32;
            $unit = '°F';
        } else {
            $temperature = $baseTemp;
            $unit = '°C';
        }

        $humidity = rand(40, 90);
        $windSpeed = rand(5, 25);

        $weatherReport = sprintf(
            "Weather for %s:\n" .
            "Condition: %s\n" .
            "Temperature: %.1f%s\n" .
            "Humidity: %d%%\n" .
            "Wind Speed: %d km/h\n" .
            "\n(Note: This is simulated data for demonstration purposes)",
            $city,
            $condition,
            $temperature,
            $unit,
            $humidity,
            $windSpeed
        );

        return new TextToolResult($weatherReport);
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
