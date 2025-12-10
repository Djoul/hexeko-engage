<?php

namespace App\Console\Commands\Translations;

use Illuminate\Console\Command;
use Spatie\DbDumper\Databases\PostgreSql;

class DumpTranslationTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:dump-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump translation tables to dump.sql';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $databaseName = config('database.connections.pgsql.database');
        $userName = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');

        $dumpPath = base_path('dump.sql');

        PostgreSql::create()
            ->setDbName(is_string($databaseName) ? $databaseName : '')
            ->setUserName(is_string($userName) ? $userName : '')
            ->setPassword(is_string($password) ? $password : '')
            ->setHost(is_string($host) ? $host : '')
            ->setPort(is_numeric($port) ? (int) $port : 5432)
            ->includeTables([
                'translation_activity_logs',
                'translation_keys',
                'translation_values',
            ])
            ->dumpToFile($dumpPath);

        $this->info('Translation tables dumped to '.$dumpPath);
    }
}
