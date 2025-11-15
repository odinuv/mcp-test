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

class MeteoDataTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'get-weather-near-location';
    }

    public function getDescription(): string
    {
        return 'Retrieves the most recent weather data from meteorological stations near a specified geographic location for today. Searches within a given radius (in kilometers) from the provided coordinates and returns measurements including temperature, humidity, pressure, wind speed/direction, and precipitation data from nearby weather stations. Results are sorted by most recent timestamp first, then by distance from the specified location.';
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
                name: 'distance',
                type: PropertyType::NUMBER,
                description: 'Search radius in kilometers from the specified coordinates. Default is 1.0 km.',
                default: '1.0',
                required: false,
            ),
            new SchemaProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of weather station readings to return. Default is 10.',
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
            title: 'Meteorological Data Tool',
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
        $distance = (float)($arguments['distance'] ?? 1.0);
        $limit = min(max((int)($arguments['limit'] ?? 10), 1), 100);

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

        if ($distance < 0.1 || $distance > 100) {
            return new TextToolResult('Error: distance must be between 0.1 and 100 km');
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
            // Filter for data from 2025-11-11 and within distance radius
            // Cast LAT and LON to DOUBLE PRECISION for radians() function
            // Use subquery to filter by calculated distance
            $sql = '
                SELECT * FROM (
                    SELECT
                        "LAT", "LON", "NAME", "TIMESTAMP", "PRESSURE", "HUMIDITY",
                        "WIND_DIRECTION", "WIND_SPEED", "TEMPERATURE_ADULT", "TEMPERATURE_CHILD",
                        "SOLAR_RADIATION", "PRECIPITATION",
                        (
                            6371 * acos(
                                cos(radians(:latitude)) *
                                cos(radians("LAT"::DOUBLE PRECISION)) *
                                cos(radians("LON"::DOUBLE PRECISION) - radians(:longitude)) +
                                sin(radians(:latitude)) *
                                sin(radians("LAT"::DOUBLE PRECISION))
                            )
                        ) AS distance_km
                    FROM public."meteo_data"
                    WHERE "TIMESTAMP"::date = \'2025-11-11\'
                ) AS subquery
                WHERE distance_km <= :distance
                ORDER BY "TIMESTAMP" DESC, distance_km ASC
                LIMIT :limit
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':latitude', $latitude, PDO::PARAM_STR);
            $stmt->bindValue(':longitude', $longitude, PDO::PARAM_STR);
            $stmt->bindValue(':distance', $distance, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll();

            // Format the results
            if (empty($results)) {
                $output = sprintf(
                    "No meteorological data found within %.1f km of coordinates (%.4f, %.4f) for today.",
                    $distance,
                    $latitude,
                    $longitude,
                );
            } else {
                $output = sprintf(
                    "Found %d weather station reading(s) within %.1f km of (%.4f, %.4f):\n\n",
                    count($results),
                    $distance,
                    $latitude,
                    $longitude,
                );

                foreach ($results as $index => $row) {
                    $output .= sprintf(
                        "Station #%d: %s\n" .
                        "  Distance: %.2f km\n" .
                        "  Location: Lat %.4f, Lon %.4f\n" .
                        "  Timestamp: %s\n" .
                        "  Temperature: Adult %.1f°C, Child %.1f°C\n" .
                        "  Pressure: %s hPa\n" .
                        "  Humidity: %s%%\n" .
                        "  Wind: %.1f km/h from %s°\n" .
                        "  Solar Radiation: %s W/m²\n" .
                        "  Precipitation: %s mm\n\n",
                        $index + 1,
                        $row['NAME'] ?? 'N/A',
                        $row['distance_km'] ?? 0,
                        $row['LAT'] ?? 0,
                        $row['LON'] ?? 0,
                        $row['TIMESTAMP'] ?? 'N/A',
                        $row['TEMPERATURE_ADULT'] ?? 0,
                        $row['TEMPERATURE_CHILD'] ?? 0,
                        $row['PRESSURE'] ?? 'N/A',
                        $row['HUMIDITY'] ?? 'N/A',
                        $row['WIND_SPEED'] ?? 0,
                        $row['WIND_DIRECTION'] ?? 'N/A',
                        $row['SOLAR_RADIATION'] ?? 'N/A',
                        $row['PRECIPITATION'] ?? 'N/A',
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
