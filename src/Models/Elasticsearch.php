<?php

namespace Mawebcoder\Elasticsearch\Models;

class Elasticsearch extends BaseElasticsearchModel
{
    const INDEX_NAME = 'test';

    public const KEY_NAME = 'name';
    public const KEY_IS_ACTIVE = 'is_active';
    public const KEY_DESCRIPTION = 'description';
    public const KEY_AGE = 'age';

    public function getIndex(): string
    {
        return self::INDEX_NAME;
    }
}