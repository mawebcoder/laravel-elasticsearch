<?php

namespace Tests\Dummy;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
class MockModel extends BaseElasticsearchModel
{

    public function getIndex(): string
    {
        return  'mock';
    }
}