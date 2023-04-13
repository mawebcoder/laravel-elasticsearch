<?php

namespace Mawebcoder\Elasticsearch;

use Illuminate\Support\ServiceProvider;
use Mawebcoder\Elasticsearch\Commands\MigrateElasticsearchMigrationsCommand;
use Mawebcoder\Elasticsearch\Commands\MigrateStatusElasticsearch;
use Mawebcoder\Elasticsearch\Commands\MigrationRollbackElasticsearchCommand;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequestInterface;

class ElasticsearchServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->commands([
            MigrateElasticsearchMigrationsCommand::class,
            MigrationRollbackElasticsearchCommand::class,
            MigrateStatusElasticsearch::class
        ]);

        $this->app->bind(ElasticHttpRequestInterface::class, ElasticApiService::class);
    }

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/configs/elasticsearch.php', 'elasticsearch');

        $this->publishes([
            __DIR__ . '/Migration/2023_03_26_create_elastic_search_migrations_logs_table.php' => database_path(
                'migrations/2023_03_26_create_elastic_search_migrations_logs_table.php'
            )
        ], 'elastic');
    }


}