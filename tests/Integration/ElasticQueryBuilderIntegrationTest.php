<?php

namespace Tests\Integration;

use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\ElasticsearchServiceProvider;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use Orchestra\Testbench\TestCase;


class ElasticQueryBuilderIntegrationTest extends TestCase
{
   protected Elasticsearch $elastic;

    protected function getPackageProviders($app): array
    {
        return [
          ElasticsearchServiceProvider::class,
        ];
   }


    /**
     * @throws RequestException
     * @throws \ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function test_elastic_create(): void
    {
//        $mock = $this->getMockBuilder(Elasticsearch::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $mock->method('create')->willReturn($mock);

        $options = [
            'id' => 1,
            'name' => 'test'
        ];

        $this->elastic = new Elasticsearch();

        $result = $this->elastic->create($options);

        $this->assertInstanceOf(Elasticsearch::class, $result);
    }
}