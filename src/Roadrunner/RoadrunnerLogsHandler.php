<?php

declare(strict_types=1);

namespace App\Roadrunner;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadrunnerLogger;

class RoadrunnerLogsHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly RoadrunnerLogger $roadrunnerLogger,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        // @phpstan-ignore-next-line because `formatted` as typed as `mixed`
        $this->roadrunnerLogger->log((string) $record->formatted);
    }
}
