<?php

namespace Mawebcoder\Elasticsearch;

use Illuminate\Support\ServiceProvider;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequest;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequestInterface;

class ElasticsearchServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind(ElasticHttpRequestInterface::class, ElasticHttpRequest::class);

        $this->publishes([
            __DIR__ . '/configs/elasticsearch.php' => config_path('elasticsearch.php')
        ]);
    }
}