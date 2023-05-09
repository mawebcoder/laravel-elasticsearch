<?php

namespace Mawebcoder\Elasticsearch\Models;

class Elasticsearch extends BaseElasticsearchModel
{

    public function getIndex(): string
    {
        return 'test';
    }
}