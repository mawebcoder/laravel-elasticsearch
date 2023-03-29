<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;

class MigrateStatusElasticsearch extends Command
{
    protected $signature = 'elastic:migrate-status';

    protected $description = 'get status of the migrations';


    public function handle(): void
    {
        $allAvailableMigrationsPath = ElasticApiService::$migrationsPath;

        $allAvailableMigrations = [];

        foreach ($allAvailableMigrationsPath as $path) {
            $files = File::files($path);

            foreach ($files as $file) {
                $allAvailableMigrations[] = $file->getPath() . '/' . $file->getFilename();
            }
        }

        $allMigratedMigrations = DB::table('elastic_search_migrations_logs')
            ->select('migrations')->get()->pluck('migrations')->toArray();

        $mapper = [];

        foreach ($allAvailableMigrations as $migrationPath) {
            if (in_array($migrationPath, $allMigratedMigrations)) {
                $mapper[$migrationPath] = ['is_migrated' => true];
                continue;
            }
            $mapper[$migrationPath] = ['is_migrated' => false];
        }

        foreach ($mapper as $key => $map) {
            if ($map['is_migrated']) {
                $this->info($key) . $this->info(' => RUN');
                continue;
            }
            $this->info($key) . $this->warn('=> PENDING');
        }
    }
}