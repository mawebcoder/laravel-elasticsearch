<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class MigrateElasticsearchMigrationsCommand extends Command
{
    protected $signature = 'elastic:migrate {--reset} {--fresh}';
    protected $description = 'migrate all loaded elasticsearch migrations';

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->option('reset')) {
            $this->reset();
            return;
        }

        if ($this->option('fresh')) {
            $this->fresh();
            return;
        }

        $this->implementMigrating();
    }

    private function isMigratedAlready(string $path): bool
    {
        return boolval(DB::table('elastic_search_migrations_logs')->where('migrations', $path)->first());
    }

    private function getUnMigratedFiles(array $migrationsPath): array
    {
        $migratedFiles = DB::table('elastic_search_migrations_logs')->select('migrations')
            ->get()->pluck('migrations')->toArray();

        $migrations = [];

        foreach ($migrationsPath as $path) {
            $files = File::files($path);

            foreach ($files as $file) {
                $migrations[] = $file->getPath() . '/' . $file->getFilename();
            }
        }

        return array_diff($migrations, $migratedFiles);
    }


    private function registerMigrationIntoLog(string $path, int $latestBatch)
    {
        DB::table('elastic_search_migrations_logs')->insert([
            'batch' => $latestBatch,
            'migrations' => $path
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function reset(): void
    {
        $migrations = DB::table('elastic_search_migrations_logs')
            ->orderBy('batch')
            ->get();

        foreach ($migrations as $migration) {
            try {
                DB::beginTransaction();

                $this->warn('reset : ' . $migration->migrations);

                $path = $migration->migrations;

                /**
                 * @type BaseElasticMigration $result
                 */
                $result = require_once $path;

                DB::table('elastic_search_migrations_logs')
                    ->where('migrations', $migration->migrations)
                    ->delete();

                $result->down();

                $this->info('reset done : ' . $migration->migrations);

                DB::commit();
            } catch (Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }
        }
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function fresh()
    {
        try {
            DB::beginTransaction();


            $this->warn('dropping all indices');

            DB::table('elastic_search_migrations_logs')
                ->delete();

            $allMigrationsPath = $this->getUnMigratedFiles(ElasticApiService::$migrationsPath);

            $allIndices = Elasticsearch::getAllIndexes();

            /**
             * remove indices from elasticsearch
             */
            foreach ($allMigrationsPath as $path) {
                /**
                 * @type BaseElasticMigration $migrationObject
                 */
                $migrationObject = require $path;

                $index = (new ReflectionClass($migrationObject->getModel()))->newInstance()->getIndex();

                if (!in_array($index, $allIndices)) {
                    continue;
                }

                $migrationObject->down();
            }

            $this->info('all indices dropped');

            foreach ($allMigrationsPath as $path) {

                $result = require $path;

                $this->info('migrating : ' . $path);

                $this->registerMigrationIntoLog($path, 1);

                $result->up();

                $this->warn('migrated : ' . $path);
            }
            $this->info('migrating done');
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function setMigration(string $path, int $latestBatch): void
    {
        if ($this->isMigratedAlready($path)) {
            return;
        }

        try {
            DB::beginTransaction();
            /**
             * @type BaseElasticMigration $result
             */
            $result = require_once $path;

            if (!$result instanceof BaseElasticMigration) {
                return;
            }

            $this->warn('migrating : ' . $path);

            $this->registerMigrationIntoLog($path, $latestBatch);

            $result->up();

            $this->info('migrated : ' . $path);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw  $exception;
        }
    }

    /**
     * @return Collection
     */
    public function getMigrationsLogs(): Collection
    {
        return DB::table('elastic_search_migrations_logs')
            ->get();
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function implementMigrating(): void
    {
        $migrationsPath = ElasticApiService::$migrationsPath;

        $unMigratedFiles = $this->getUnMigratedFiles($migrationsPath);

        if (empty($migrationsPath) || empty($unMigratedFiles)) {
            $this->info('nothing to migrate');
            return;
        }

        $latestBatch = 0;

        $latestMigrationRecord = DB::table('elastic_search_migrations_logs')
            ->orderBy('batch', 'desc')->first();

        if ($latestMigrationRecord) {
            $latestBatch = $latestMigrationRecord->batch;
        }

        $latestBatch += 1;


        foreach ($unMigratedFiles as $path) {
            $this->setMigration($path, $latestBatch);
        }
    }


}