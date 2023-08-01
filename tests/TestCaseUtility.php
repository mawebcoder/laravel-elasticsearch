<?php

namespace Tests;

use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

trait TestCaseUtility
{
    public function insertElasticDocument(BaseElasticsearchModel $model, array $data): BaseElasticsearchModel
    {
        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        $result = $model->save();

        sleep(1);

        return $result;
    }

}