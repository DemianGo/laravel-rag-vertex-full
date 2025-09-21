<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        
        return $app;
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Cria o arquivo SQLite se não existir
        $dbPath = '/tmp/laravel-testing.sqlite';
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }
        
        // Define configurações essenciais para testes usando a instância de config já existente
        $this->app['config']->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'app.env' => 'testing',
            'app.debug' => true,
        ]);
    }

    /**
     * Limpa depois de cada teste.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}