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

class SentimentDataTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'get_location_sentiment';
    }

    public function getDescription(): string
    {
        return 'Retrieves sentiment data (good, bad, or neutral) for a specific geographic location from the sentiment database. Queries the sentiment-data table to find sentiment records matching the given coordinates and sentiment type. Returns relevant sentiment information including feature IDs, timestamps, and any associated comments.';
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
                name: 'sentiment_type',
                type: PropertyType::STRING,
                description: 'Type of sentiment to filter by. Options: \'good\' for good sentiment, \'bad\' for bad sentiment, \'neutral\' for neutral sentiment.',
                enum: ['good', 'bad', 'neutral'],
                default: 'good',
                required: false,
            ),
            new SchemaProperty(
                name: 'radius_km',
                type: PropertyType::NUMBER,
                description: 'Optional search radius in kilometers from the specified coordinates. If provided, returns all sentiment records within this distance. Default is exact match or nearest point.',
                default: '1.0',
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
            title: 'Location Sentiment Data Tool',
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
        $sentimentType = strtolower($arguments['sentiment_type'] ?? 'good');
        $radiusKm = (float)($arguments['radius_km'] ?? 1.0);

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

        if (!in_array($sentimentType, ['good', 'bad', 'neutral'])) {
            return new TextToolResult('Error: sentiment_type must be either \'good\', \'bad\', or \'neutral\'');
        }

        if ($radiusKm < 0) {
            return new TextToolResult('Error: radius_km must be non-negative');
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
            // Filter by sentiment type (class column) and within radius
            // Use case-insensitive comparison for class column
            $sql = '
                SELECT * FROM (
                    SELECT
                        "feature_id", "class", "lat", "long", "datetime", "starttime_converted", "comments",
                        "name", "url",
                        (
                            6371 * acos(
                                cos(radians(:latitude)) *
                                cos(radians("lat"::DOUBLE PRECISION)) *
                                cos(radians("long"::DOUBLE PRECISION) - radians(:longitude)) +
                                sin(radians(:latitude)) *
                                sin(radians("lat"::DOUBLE PRECISION))
                            )
                        ) AS distance_km
                    FROM public."sentiment-data"
                    WHERE LOWER("class") = :sentiment_type
                ) AS subquery
                WHERE distance_km <= :radius_km
                ORDER BY "datetime" DESC, distance_km ASC
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':latitude', $latitude, PDO::PARAM_STR);
            $stmt->bindValue(':longitude', $longitude, PDO::PARAM_STR);
            $stmt->bindValue(':sentiment_type', $sentimentType, PDO::PARAM_STR);
            $stmt->bindValue(':radius_km', $radiusKm, PDO::PARAM_STR);

            $stmt->execute();
            $results = $stmt->fetchAll();

            // Format the results
            if (empty($results)) {
                $output = sprintf(
                    "No %s sentiment data found within %.1f km of coordinates (%.4f, %.4f).",
                    $sentimentType,
                    $radiusKm,
                    $latitude,
                    $longitude,
                );
            } else {
                $output = sprintf(
                    "Found %d %s sentiment record(s) within %.1f km of (%.4f, %.4f):\n\n",
                    count($results),
                    $sentimentType,
                    $radiusKm,
                    $latitude,
                    $longitude,
                );

                foreach ($results as $index => $row) {
                    $output .= sprintf(
                        "Record #%d (Feature ID: %s)\n" .
                        "  Distance: %.2f km\n" .
                        "  Location: Lat %.4f, Lon %.4f\n" .
                        "  Name: %s\n" .
                        "  URL: %s\n" .
                        "  Sentiment: %s\n" .
                        "  DateTime: %s\n" .
                        "  Start Time: %s\n" .
                        "  Comments: %s\n\n",
                        $index + 1,
                        $row['feature_id'] ?? 'N/A',
                        $row['distance_km'] ?? 0,
                        $row['lat'] ?? 0,
                        $row['long'] ?? 0,
                        !empty($row['name']) ? $row['name'] : '(No name)',
                        !empty($row['url']) ? $row['url'] : '(No URL)',
                        $row['class'] ?? 'N/A',
                        $row['datetime'] ?? 'N/A',
                        $row['starttime_converted'] ?? 'N/A',
                        !empty($row['comments']) ? $row['comments'] : '(No comments)',
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
