<?php

declare(strict_types=1);

namespace App\Roadrunner;

use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use RoadRunner\Logger\Logger;
use Runtime\RoadRunnerSymfonyNyholm\Runtime;
use Spiral\Goridge\RPC\RPC;

class LogsHandlerFactory
{
    public function __construct(
        private readonly ?string $appRuntime,
    ) {
    }

    public function createHandler(): FormattableHandlerInterface
    {
        if ($this->isBehindRoadrunner()) {
            // defined in .rr.yaml
            $handler = new RoadrunnerLogsHandler(new Logger(RPC::create('unix:///code/var/rr.socket')));
        } else {
            $handler = new StreamHandler('php://stderr');
        }

        $handler->pushProcessor(new PsrLogMessageProcessor());
        return $handler;
    }

    private function isBehindRoadrunner(): bool
    {
        return $this->appRuntime === Runtime::class;
    }
}
