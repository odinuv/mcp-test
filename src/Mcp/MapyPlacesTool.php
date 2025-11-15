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

class MapyPlacesTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'get-places-of-interest';
    }

    public function getDescription(): string
    {
        return 'Retrieves places of interest near specified geographic coordinates using the Mapy.cz geocoding API. Returns information about nearby locations including names, types, addresses, and coordinates. Search radius can be customized (default 300 meters).';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'latitude',
                type: PropertyType::NUMBER,
                description: 'Latitude coordinate in decimal degrees (e.g., 49.5913 for Olomouc, Czech Republic). Range: -90 to 90.',
                required: true,
            ),
            new SchemaProperty(
                name: 'longitude',
                type: PropertyType::NUMBER,
                description: 'Longitude coordinate in decimal degrees (e.g., 17.2634 for Olomouc, Czech Republic). Range: -180 to 180.',
                required: true,
            ),
            new SchemaProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of places to return. Default is 10.',
                default: '10',
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
            title: 'Mapy.cz Places of Interest Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: true,
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        // Read API key from environment variable
        $apiKey = getenv('MAPY_API_KEY') ?: '';

        if (empty($apiKey)) {
            return new TextToolResult('Error: Missing required environment variable MAPY_API_KEY');
        }

        // Parse and validate arguments
        $latitude = $arguments['latitude'] ?? null;
        $longitude = $arguments['longitude'] ?? null;
        $limit = max((int)($arguments['limit'] ?? 10), 1);

        if ($latitude === null || $longitude === null) {
            return new TextToolResult('Error: latitude and longitude are required parameters');
        }

        $latitude = (float)$latitude;
        $longitude = (float)$longitude;

        if ($latitude < -90 || $latitude > 90) {
            return new TextToolResult('Error: latitude must be between -90 and 90');
        }

        if ($longitude < -180 || $longitude > 180) {
            return new TextToolResult('Error: longitude must be between -180 and 180');
        }

        try {
            // Build API URL with parameters
            $apiUrl = 'https://api.mapy.com/v1/geocode';

            // Build query string manually to support multiple type parameters and multiple preferNear parameters
            $queryParams = [
                'query' => 'park, kostel',
                'lang' => 'cs',
                'limit' => $limit,
            ];

            // Add multiple type parameters
            $types = [
                'regional',
                'regional.country',
                'regional.region',
                'regional.municipality',
                'regional.municipality_part',
                'regional.street',
                'regional.address',
                'poi',
            ];

            $queryString = http_build_query($queryParams);

            foreach ($types as $type) {
                $queryString .= '&type=' . urlencode($type);
            }

            // Add preferNear parameters (longitude first, then latitude) with 300m precision
            $queryString .= '&preferNear=' . urlencode((string)$longitude);
            $queryString .= '&preferNear=' . urlencode((string)$latitude);
            $queryString .= '&preferNearPrecision=300';

            $url = $apiUrl . '?' . $queryString;

            // Make HTTP request using file_get_contents with API key in header
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/json\r\n" .
                                "X-Mapy-Api-Key: " . $apiKey . "\r\n",
                    'timeout' => 10,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return new TextToolResult('Error: Failed to connect to Mapy.cz API');
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new TextToolResult('Error: Invalid JSON response from API');
            }

            // Check for API errors
            if (isset($data['error'])) {
                return new TextToolResult(sprintf('API Error: %s', $data['error']));
            }

            // Parse results
            $items = $data['items'] ?? [];

            if (empty($items)) {
                $output = sprintf(
                    "No places of interest found near coordinates (%.4f, %.4f) within 300m radius.",
                    $latitude,
                    $longitude
                );
            } else {
                $output = sprintf(
                    "Found %d place(s) of interest near (%.4f, %.4f) within 300m radius:\n\n",
                    count($items),
                    $latitude,
                    $longitude
                );

                foreach ($items as $index => $item) {
                    $name = $item['name'] ?? 'N/A';
                    $type = $item['type'] ?? 'N/A';
                    $position = $item['position'] ?? [];
                    $lat = $position['lat'] ?? 'N/A';
                    $lon = $position['lon'] ?? 'N/A';
                    $label = $item['label'] ?? '';

                    // Location is a string field with the full address
                    $addressStr = $item['location'] ?? 'N/A';

                    // Fallback to building address from regional data if location is not available
                    if ($addressStr === 'N/A') {
                        $regional = $item['regional'] ?? [];
                        $address = [];

                        if (!empty($regional['address'])) {
                            $address[] = $regional['address'];
                        }
                        if (!empty($regional['city'])) {
                            $address[] = $regional['city'];
                        }
                        if (!empty($regional['zip'])) {
                            $address[] = $regional['zip'];
                        }

                        $addressStr = !empty($address) ? implode(', ', $address) : 'N/A';
                    }

                    $output .= sprintf(
                        "%d. %s\n" .
                        "   Type: %s\n" .
                        "   Location: Lat %.6f, Lon %.6f\n" .
                        "   Address: %s\n",
                        $index + 1,
                        $name,
                        $type,
                        $lat,
                        $lon,
                        $addressStr
                    );

                    if (!empty($label)) {
                        $output .= sprintf("   Label: %s\n", $label);
                    }

                    $output .= "\n";
                }
            }

            return new TextToolResult($output);

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
