<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.sqlite.database');

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Unsafe test database configuration blocked. Tests must use an in-memory SQLite database. '
                .'Run "php artisan config:clear" before testing.'
            );
        }

        return $app;
    }
}
