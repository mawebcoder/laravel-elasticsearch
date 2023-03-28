<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use ReflectionException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class MigrateElasticsearchMigrationsCommand extends Command
{
    protected $signature = 'elastic:migrate {--reset} {--fresh} {--rollback=1}';

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
        }

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


        foreach ($migrationsPath as $path) {
            $files = File::files($path);

            $this->setMigration($files, $latestBatch);
        }
    }

    private function isMigratedAlready(string $fileName): bool
    {
        return boolval(DB::table('elastic_search_migrations_logs')->where('migrations', $fileName)->first());
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
        try {
            DB::beginTransaction();

            $migrations = DB::table('elastic_search_migrations_logs')
                ->orderBy('batch')
                ->get();

            foreach ($migrations as $migration) {
                $this->info('reset => ' . $migration);

                $path = $migrations->migration;

                /**
                 * @type BaseElasticMigration $result
                 */
                $result = require_once $path;

                $result->down();

                $this->info('reset done => ' . $migration);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw  $exception;
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

            $this->reset();

            $this->warn('dropping all indices');

            DB::table('elastic_search_migrations_logs')
                ->delete();

            $this->info('migrating.please wait to finish');

            Artisan::call('elastic:migrate');

            $this->info('migrating done');

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $this->info('dropping all indices done');
    }

    /**
     * @throws Throwable
     */


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function setMigration(array $files, int $latestBatch)
    {
        foreach ($files as $file) {
            if ($this->isMigratedAlready($file->getFilename())) {
                continue;
            }

            try {
                DB::beginTransaction();

                $path = $file->getPath() . '/' . $file->getFilename();

                /**
                 * @type BaseElasticMigration $result
                 */
                $result = require_once $path;

                if (!$result instanceof BaseElasticMigration) {
                    continue;
                }

                $this->info('migrating => ' . $path);

                $this->registerMigrationIntoLog($path, $latestBatch);

                $result->up();

                $this->info('migrated => ' . $path);

                DB::commit();
            } catch (Throwable $exception) {
                DB::rollBack();
                throw  $exception;
            }
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


}