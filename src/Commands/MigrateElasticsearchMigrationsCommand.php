<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use ReflectionClass;
use ReflectionException;
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
    public function handle(ElasticApiService $elasticApiService): void
    {
        if ($this->option('reset')) {
            $this->reset($elasticApiService);
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
                $migrationObject = require $file->getRealPath();

                if (!$migrationObject instanceof BaseElasticMigration) {
                    continue;
                }
                $migrations[] = $file->getRealPath();
            }
        }

        return array_diff($migrations, $migratedFiles);
    }


    private function registerMigrationIntoLog(string $path, int $latestBatch, string $index)
    {
        DB::table('elastic_search_migrations_logs')->insert([
            'batch' => $latestBatch,
            'migrations' => $path,
            'index' => $index
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function reset(ElasticApiService $elasticApiService): void
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
                $result = require $path;

                DB::table('elastic_search_migrations_logs')
                    ->where('migrations', $migration->migrations)
                    ->delete();

                $result->down($elasticApiService);

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
    public function fresh(ElasticApiService $elasticApiService): void
    {
        try {
            DB::beginTransaction();


            $this->warn('dropping all indices');

            DB::table('elastic_search_migrations_logs')
                ->delete();

            $allMigrationsPath = $this->getUnMigratedFiles(ElasticApiService::$migrationsPath);

            $allIndices = $elasticApiService->getAllIndexes();

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

                $migrationObject->down($elasticApiService);
            }

            $this->info('all indices dropped');

            foreach ($allMigrationsPath as $path) {
                /**
                 * @type BaseElasticMigration $result
                 */
                $result = require $path;

                $index = $this->getIndex($result->getModel());

                $this->info('migrating : ' . $path);

                $this->registerMigrationIntoLog($path, 1, $index);

                $result->up($elasticApiService);

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
     */
    private function getIndex(string $model): string
    {
        /**
         * @type BaseElasticsearchModel $object
         */
        $object = (new ReflectionClass($model))->newInstance();

        return $object->getIndex();
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function setMigration(string $path, int $latestBatch,ElasticApiService $elasticApiService): void
    {
        if ($this->isMigratedAlready($path)) {
            return;
        }

        try {
            DB::beginTransaction();
            /**
             * @type BaseElasticMigration $result
             */
            $result = require $path;

            if (!$result instanceof BaseElasticMigration) {
                return;
            }

            $index = $this->getIndex($result->getModel());

            $this->warn('migrating : ' . $path);

            $this->registerMigrationIntoLog($path, $latestBatch, $index);

            $result->up($elasticApiService);

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