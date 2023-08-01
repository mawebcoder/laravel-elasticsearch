<?php

namespace Mawebcoder\Elasticsearch\Models;

class Elasticsearch extends BaseElasticsearchModel
{

    public const FILED_NAME = 'name';
    public const FILED_IS_ACTIVE = 'is_active';
    public const FILED_DESCRIPTION = 'description';
    public const FILED_AGE = 'age';

    public function getIndex(): string
    {
        return 'test';
    }
}