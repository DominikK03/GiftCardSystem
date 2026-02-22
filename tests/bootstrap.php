<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env');
    // Force test DATABASE_URL — Docker Compose sets it as real env var which
    // normally takes precedence over .env.test. Override it explicitly.
    (new Dotenv())->usePutenv()->overload(dirname(__DIR__).'/.env.test');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
