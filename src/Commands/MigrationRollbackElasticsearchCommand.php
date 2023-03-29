<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrationRollbackElasticsearchCommand extends Command
{
    protected $signature = 'elastic:migrate-rollback {--step=1}';

    protected $description = 'rollback migration';

    /**
     * @throws Throwable
     */
    public function handle()
    {
        $steps = $this->option('step');

        collect(range(1, $steps))->each(function () {
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

                    $object = require_once $path;

                    $object->down();
                }

                DB::commit();
            } catch (Throwable $exception) {
                DB::rollBack();

                throw  $exception;
            }
        });
    }
}