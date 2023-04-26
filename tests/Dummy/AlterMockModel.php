<?php

namespace Tests\Dummy;

use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

class AlterMockModel extends BaseElasticsearchModel
{

    public function getIndex(): string
    {
        return 'alter-mock';
    }
}