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
    private function registerSomeRecords(): array
    {
        $data = [
            'id' => 1,
            'name' => 'ahmad',
            'age' => 22,
            'description' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'name' => 'jafar',
            'age' => 26,
            'description' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'age' => 30,
            'name' => 'ali',
            'description' => 'number 3'
        ];

        $elasticModelOne = new elasticModel();

        $elasticModelOne = $this->insertElasticDocument($elasticModelOne, $data);

        $elasticModelTwo = new elasticModel();

        $elasticModelTwo = $this->insertElasticDocument($elasticModelTwo, $data2);

        $elasticModelThree = new elasticModel();

        $elasticModelThree = $this->insertElasticDocument($elasticModelThree, $data3);

        return [
            $elasticModelOne,
            $elasticModelTwo,
            $elasticModelThree
        ];
    }

}