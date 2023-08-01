<?php

namespace Tests;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use ReflectionException;

trait TestCaseUtility
{
    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function insertElasticDocument(BaseElasticsearchModel $model, array $data): BaseElasticsearchModel
    {
        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        $result = $model->save();

        sleep(1);

        return $result;
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    private function registerSomeRecords():array
    {
        $data = [
            'id' => 1,
            'name' => 'ahmad',
            'description' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'name' => 'jafar',
            'description' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'description' => 'number 3'
        ];

        $elasticModelOne = new elasticModel();

        $this->insertElasticDocument($elasticModelOne, $data);

        $elasticModelTwo = new elasticModel();

        $this->insertElasticDocument($elasticModelTwo, $data2);

        $elasticModelThree = new elasticModel();

        $this->insertElasticDocument($elasticModelThree, $data3);

        return [
            $elasticModelOne,
            $elasticModelTwo,
            $elasticModelThree
        ];
    }

}