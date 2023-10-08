<?php

namespace Mawebcoder\Elasticsearch;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use ReflectionException;

final class ElasticSchema
{
    /**
     * @param string $model
     * @return void
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function deleteIndexIfExists(string $model): void
    {
        if (self::isExistsIndex($model)) {
            self::deleteIndex($model);
        }
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public static function deleteIndex(string $model): void
    {
        $elasticAPIService = new ElasticApiService();

        $elasticAPIService->setModel($model)->delete();
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     */
    public static function isExistsIndex(string $model): bool
    {
        /**
         * @type BaseElasticsearchModel $model
         */

        $index = $model::newQuery()->getIndexWithPrefix();

        return Elasticsearch::hasIndex($index);
    }
}