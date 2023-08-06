<?php

namespace Mawebcoder\Elasticsearch;

use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

final class ElasticSchema
{
    public static function deleteIndexIfExists(string $model)
    {
        /* @var BaseElasticsearchModel $model*/
        if ($model::newQuery()->isExistsIndex()) {
            self::deleteIndex($model);
        }
    }

    public static function deleteIndex(string $model): void
    {
        $elasticAPIService = new ElasticApiService();
        $elasticAPIService->setModel($model)->delete();
    }
}