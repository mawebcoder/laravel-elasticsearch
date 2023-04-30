<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Throwable;

class MigrationRollbackElasticsearchCommand extends Command
{
    protected $signature = 'elastic:migrate-rollback {--step=1}';

    protected $description = 'rollback migration';

    /**
     * @throws Throwable
     */
    public function handle(ElasticApiService $elasticApiService)
    {
        $steps = $this->option('step');

        collect(range(1, $steps))->each(function () use ($elasticApiService) {
            try {
                DB::beginTransaction();

                $latestBatchValue = DB::table('elastic_search_migrations_logs')
                    ->select('batch')->orderBy('batch', 'desc')
                    ->first();

                if (!$latestBatchValue) {
                    return;
                }

                $migrations = DB::table('elastic_search_migrations_logs')
                    ->where('batch', $latestBatchValue->batch)
                    ->get();

                foreach ($migrations as $migration) {
                    $path = $migration->migrations;

                    DB::table('elastic_search_migrations_logs')->where('id', $migration->id)->delete();

                    /**
                     * @type BaseElasticMigration $object
                     */
                    $object = require_once $path;

                    $object->down($elasticApiService);
                }

                DB::commit();
            } catch (Throwable $exception) {
                DB::rollBack();

                throw  $exception;
            }
        });
    }
}