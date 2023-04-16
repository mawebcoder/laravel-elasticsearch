<?php

namespace Mawebcoder\Elasticsearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

class Test extends BaseElasticsearchModel
{
    use HasFactory;


    public function getIndex(): string
    {
        return 'test';
    }
}
