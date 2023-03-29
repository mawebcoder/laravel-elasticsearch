<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;

class MigrateStatusElasticsearch extends Command
{
    protected $signature = 'elastic:migrate-status';

    protected $description = 'get status of the migrations';


    public function handle()
    {
        //@todo
    }
}