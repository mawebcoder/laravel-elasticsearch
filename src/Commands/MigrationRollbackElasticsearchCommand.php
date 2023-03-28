<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;

class MigrationRollbackElasticsearchCommand extends Command
{
    protected $signature = 'elastic:migrate-rollback {--step=1}';


    protected $description = 'rollback migration';

    public function handle()
    {
        //@todo rollback here
    }
}