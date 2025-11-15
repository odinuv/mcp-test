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

class MeteoStationTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'find-nearby-meteostations';
    }

    public function getDescription(): string
    {
        return 'Find meteostations within a specified distance from given coordinates. Uses the Haversine formula to calculate distances between coordinates and returns stations sorted by proximity.';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'latitude',
                type: PropertyType::NUMBER,
                description: 'Latitude of the search origin point in decimal degrees (e.g., 49.5938)',
                required: true,
            ),
            new SchemaProperty(
                name: 'longitude',
                type: PropertyType::NUMBER,
                description: 'Longitude of the search origin point in decimal degrees (e.g., 17.2509)',
                required: true,
            ),
            new SchemaProperty(
                name: 'distance_km',
                type: PropertyType::NUMBER,
                description: 'Search radius in kilometers from the given coordinates',
                default: '1',
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
            title: 'Meteorological Station Finder Tool',
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
        $latitude = $arguments['latitude'] ?? null;
        $longitude = $arguments['longitude'] ?? null;
        $distanceKm = (float)($arguments['distance_km'] ?? 1.0);

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

        if ($distanceKm < 0.1 || $distanceKm > 100) {
            return new TextToolResult('Error: distance_km must be between 0.1 and 100 km');
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

            // Build query with Haversine formula for distance calculation
            // Cast LAT and LON to DOUBLE PRECISION for radians() function
            // Use subquery to filter by calculated distance
            $sql = '
                SELECT * FROM (
                    SELECT
                        "NAZEV", "LAT", "LON",
                        (
                            6371 * acos(
                                cos(radians(:latitude)) *
                                cos(radians("LAT"::DOUBLE PRECISION)) *
                                cos(radians("LON"::DOUBLE PRECISION) - radians(:longitude)) +
                                sin(radians(:latitude)) *
                                sin(radians("LAT"::DOUBLE PRECISION))
                            )
                        ) AS distance_km
                    FROM public."meteostanice_mesta_Olomouc"
                ) AS subquery
                WHERE distance_km <= :distance_km
                ORDER BY distance_km ASC
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':latitude', $latitude, PDO::PARAM_STR);
            $stmt->bindValue(':longitude', $longitude, PDO::PARAM_STR);
            $stmt->bindValue(':distance_km', $distanceKm, PDO::PARAM_STR);

            $stmt->execute();
            $results = $stmt->fetchAll();

            // Format the results
            if (empty($results)) {
                $output = sprintf(
                    "No weather stations found within %.1f km of coordinates (%.4f, %.4f).",
                    $distanceKm,
                    $latitude,
                    $longitude,
                );
            } else {
                $output = sprintf(
                    "Found %d weather station(s) within %.1f km of (%.4f, %.4f):\n\n",
                    count($results),
                    $distanceKm,
                    $latitude,
                    $longitude,
                );

                foreach ($results as $index => $row) {
                    $output .= sprintf(
                        "%d. %s\n" .
                        "   Distance: %.2f km\n" .
                        "   Location: Lat %.4f, Lon %.4f\n\n",
                        $index + 1,
                        $row['NAZEV'] ?? 'N/A',
                        $row['distance_km'] ?? 0,
                        $row['LAT'] ?? 0,
                        $row['LON'] ?? 0,
                    );
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
