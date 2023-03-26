<?php

namespace Mawebcoder\Elasticsearch;

use Illuminate\Support\ServiceProvider;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequest;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequestInterface;

class ElasticsearchServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind(ElasticHttpRequestInterface::class, ElasticHttpRequest::class);

    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/configs/elasticsearch.php' => config_path('elasticsearch.php'),
            __DIR__ . '/Migration/2023_03_26_create_elastic_search_migrations_logs_table.php' => database_path(
                'migrations/2023_03_26_create_elastic_search_migrations_logs_table.php'
            )
        ], 'elastic-configs');
    }


}