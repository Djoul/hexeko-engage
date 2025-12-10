<?php

use Composer\Autoload\ClassLoader;

// Phase 1: ParaTest isolation via PostgreSQL schemas
// This bootstrap runs before Laravel and adjusts the DB search_path per worker.

// Helper to set env vars consistently
$setEnv = static function (string $k, string $v): void {
    putenv($k.'='.$v);
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
};

// Enforce pgsql driver and strict testing environment (proactively for robustness)
$setEnv('APP_ENV', 'testing');
$setEnv('DB_CONNECTION', 'pgsql');
$setEnv('DB_HOST', 'db_engage_testing');
$setEnv('DB_PORT', '5432');
$setEnv('DB_DATABASE', 'db_engage_testing');
$setEnv('DB_USERNAME', 'root');
$setEnv('DB_PASSWORD', 'password');

// Final guards (should never fail now unless overridden afterwards)
if (getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('[bootstrap_paratest] APP_ENV must be testing; got: '.(getenv('APP_ENV') ?: 'undefined'));
}
if (getenv('DB_CONNECTION') !== 'pgsql') {
    throw new RuntimeException('[bootstrap_paratest] Only pgsql driver is supported for tests; got: '.(getenv('DB_CONNECTION') ?: 'undefined'));
}
if (getenv('DB_DATABASE') !== 'db_engage_testing') {
    throw new RuntimeException('[bootstrap_paratest] DB_DATABASE must be db_engage_testing; got: '.(getenv('DB_DATABASE') ?: 'undefined'));
}

// Determine ParaTest worker token (TEST_TOKEN is set per worker; absent or '0' for sequential)
$token = getenv('TEST_TOKEN') ?: '0';

$workerSchema = $token === '0' ? 'public' : 'test_'.$token;
$searchPath = $workerSchema.',public';

// If using a non-public schema, ensure it exists before Laravel connects
if ($workerSchema !== 'public') {
    $dbHost = getenv('DB_HOST') ?: 'db_engage_testing';
    $dbPort = getenv('DB_PORT') ?: '5432';
    $dbName = getenv('DB_DATABASE') ?: 'db_engage_testing';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: 'password';

    try {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        // Create schema if it does not exist (quoted to support safe names)
        $safeSchema = '"'.str_replace('"', '""', $workerSchema).'"';
        $pdo->exec('CREATE SCHEMA IF NOT EXISTS '.$safeSchema);
        // Ensure per-worker migrations table exists in the worker schema so Laravel
        // does not fall back to public.migrations when resolving migration state.
        $pdo->exec('SET search_path TO '.$safeSchema);
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL
        )');
        // Fix sequences after schema creation
        require_once __DIR__.'/fix_test_sequences.php';
        fixTestSequences($pdo, $workerSchema);
    } catch (Throwable $e) {
        // Do not hard-fail bootstrap; tests will report DB errors if connection is wrong
        fwrite(STDERR, '[bootstrap_paratest] Failed to ensure schema: '.$e->getMessage()."\n");
    }
}

// Export DB_SCHEMA for Laravel's pgsql connection (config/database.php reads it)
putenv('DB_SCHEMA='.$searchPath);
$_ENV['DB_SCHEMA'] = $searchPath;
$_SERVER['DB_SCHEMA'] = $searchPath;

// Ensure Composer autoload is available if phpunit.xml bootstrap is not vendor/autoload.php
if (! class_exists(ClassLoader::class)) {
    $autoload = __DIR__.'/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require $autoload;
    }
}
