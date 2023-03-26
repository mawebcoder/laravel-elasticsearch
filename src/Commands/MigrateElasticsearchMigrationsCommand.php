<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use ReflectionException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class MigrateElasticsearchMigrationsCommand extends Command
{
    protected $signature = 'elastic:migrate';

    protected $description = 'migrate all loaded elasticsearch migrations';


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function handle(): void
    {
        $migrationsPath = ElasticApiService::$migrationsPath;
        if (empty($migrationsPath)) {
            $this->info('nothing to migrate');
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

            foreach ($files as $file) {
                $fileName = $file->getFilename();

                if ($this->isMigratedAlready($fileName)) {
                    continue;
                }
                try {
                    DB::beginTransaction();

                    $this->registerMigrationIntoLog($file, $latestBatch);

                    $this->runUpMethod($file);

                    DB::commit();
                } catch (Throwable $exception) {
                    DB::rollBack();
                    throw  $exception;
                }
            }
        }
    }

    private function isMigratedAlready(string $fileName): bool
    {
        return boolval(DB::table('elastic_search_migrations_logs')->where('migration', $fileName)->first());
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function runUpMethod(SplFileInfo $file)
    {
        $path = $file->getPath() . '/' . $file->getFilename();

        /**
         * @type BaseElasticMigration $result
         */
        $result = require_once $path;

        $result->up();
    }

    private function registerMigrationIntoLog(SplFileInfo $file, int $latestBatch)
    {
        DB::table('elastic_search_migrations_logs')->insert([
            'batch' => $latestBatch,
            'migration' => $file->getFilename()
        ]);
    }
}