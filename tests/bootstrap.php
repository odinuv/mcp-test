<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$dotEnv = new Dotenv();
$dotEnv->usePutenv();
$dotEnv->bootEnv(dirname(__DIR__).'/.env', 'dev', []);
