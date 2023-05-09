<?php

namespace Mawebcoder\Elasticsearch\Facade;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequestInterface;

/**
 * @method static Response post(?string $path = null, array $data = [])
 * @method static Response get(?string $path = null)
 * @method static Response put(?string $path = null, array $data = [])
 * @method static Response delete(?string $path = null, array $data = [])
 * @method static ElasticApiService setModel(string $modelName)
 * @method static void loadMigrationsFrom(string $directory)
 * @method static Response dropModelIndex()
 * @method static array getAllIndexes()
 * @method static array getFields()
 * @method static array getMappings()
 */
class Elasticsearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ElasticHttpRequestInterface::class;
    }
}