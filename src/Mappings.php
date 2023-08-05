<?php

namespace Mawebcoder\Elasticsearch;

use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

final class Mappings
{
    public static function deleteIfExists(string $model)
    {
        if ($model::newQuery()->isExistsIndex()) {
            $elasticAPIService = new ElasticApiService();
            $elasticAPIService->setModel($model)->delete();
        }
    }
}