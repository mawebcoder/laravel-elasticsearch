<?php

namespace Mawebcoder\Elasticsearch\Facade;

use Illuminate\Support\Facades\Facade;
use Mawebcoder\Elasticsearch\Http\ElasticHttpRequestInterface;

class Elasticsearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ElasticHttpRequestInterface::class;
    }
}