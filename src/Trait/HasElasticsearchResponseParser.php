<?php

namespace Mawebcoder\Elasticsearch\Trait;

use Psr\Http\Message\ResponseInterface;

trait HasElasticsearchResponseParser
{
    const FIRST_INDEX = 0;

    const ID_LOCATION = 'Location';

    /**
     * @param ResponseInterface $response
     * @return false|string
     */
    protected function parseId(ResponseInterface $response): false|string
    {
        $segments = explode('/', $response->getHeader(self::ID_LOCATION)[self::FIRST_INDEX]);

        return end($segments);
    }
}