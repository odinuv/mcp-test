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

class RecentTrafficTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'recent-traffic';
    }

    public function getDescription(): string
    {
        return 'Retrieves the most recent traffic intensity measurements from the closest traffic monitoring station to a specified geographic location in Olomouc. Searches for the nearest measuring point to the provided coordinates and returns the 5 most recent traffic intensity records from that station, ordered by timestamp (most recent first). Each result includes the measurement timestamp, traffic intensity value, and the actual coordinates of the measuring station.';
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
                description: 'Maximum number of traffic intensity records to return. Default is 5.',
                default: '5',
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
            title: 'Recent Traffic Intensity Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        // Parse and validate arguments
        $latitude = $arguments['latitude'] ?? null;
        $longitude = $arguments['longitude'] ?? null;
        $limit = max((int)($arguments['limit'] ?? 5), 1);

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

        // Read credentials from environment variables
        $host = getenv('POSTGRES_HOST') ?: '';
        $port = (int)(getenv('POSTGRES_PORT') ?: 5432);
        $database = getenv('POSTGRES_DATABASE') ?: '';
        $username = getenv('POSTGRES_USERNAME') ?: '';
        $password = getenv('POSTGRES_PASSWORD') ?: '';

        if (empty($host) || empty($database) || empty($username)) {
            return new TextToolResult('Error: Missing required environment variables (POSTGRES_HOST, POSTGRES_DATABASE, POSTGRES_USERNAME)');
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

            // Query to find the nearest measurement point and get its most recent records
            // Using simple Euclidean distance for proximity (for more accuracy, consider PostGIS)
            // Cast lat/lon to numeric since they are stored as character varying
            $sql = '
                WITH nearest_point AS (
                    SELECT DISTINCT lat, lon, name,
                           SQRT(POWER(CAST(lat AS NUMERIC) - :input_lat, 2) + POWER(CAST(lon AS NUMERIC) - :input_lon, 2)) AS distance
                    FROM "dopravni-processed"
                    ORDER BY distance
                    LIMIT 1
                )
                SELECT d.time, d.intensity, d.lat, d.lon, d.name
                FROM "dopravni-processed" d
                INNER JOIN nearest_point n ON d.lat = n.lat AND d.lon = n.lon
                ORDER BY d.time DESC
                LIMIT :limit;
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':input_lat', $latitude, PDO::PARAM_STR);
            $stmt->bindValue(':input_lon', $longitude, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll();

            // Format the results
            if (empty($results)) {
                $output = sprintf(
                    'No traffic measurement data found near coordinates (%.6f, %.6f).',
                    $latitude,
                    $longitude
                );
            } else {
                // All records are from the same measurement point, so use the first record's coordinates
                $stationLat = $results[0]['lat'];
                $stationLon = $results[0]['lon'];
                $stationName = $results[0]['name'] ?? 'Unknown';

                $output = sprintf(
                    "Nearest traffic measurement station: %s\n" .
                    "Station location: Lat %.6f, Lon %.6f\n" .
                    "Searched near: Lat %.6f, Lon %.6f\n\n" .
                    "%d most recent traffic intensity measurements:\n\n",
                    $stationName,
                    $stationLat,
                    $stationLon,
                    $latitude,
                    $longitude,
                    count($results)
                );

                foreach ($results as $index => $row) {
                    $time = $row['time'] ?? 'N/A';
                    $intensity = $row['intensity'] ?? 'N/A';

                    $output .= sprintf(
                        "%d. Time: %s\n" .
                        "   Intensity: %s\n\n",
                        $index + 1,
                        $time,
                        $intensity
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
