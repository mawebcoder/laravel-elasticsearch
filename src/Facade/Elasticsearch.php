<?php

namespace Mawebcoder\Elasticsearch\Facade;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequest;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequestInterface;

/**
 * @method static Response post(?string $path = null, array $data = [])
 * @method static Response get(?string $path = null)
 * @method static Response put(?string $path = null,array $data = [])
 * @method static Response delete(?string $path = null,array $data = [])
 * @method static ElasticHttpRequest setModel(string $modelName)
 */
class Elasticsearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ElasticHttpRequestInterface::class;
    }
}