<?php

namespace Tests;

use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use PHPUnit\Framework\TestCase;

class ElasticQueryBuilderIntegrationTest extends TestCase
{
    /**
     * @throws RequestException
     * @throws \ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function test_elastic_create()
    {
        $mock = $this->getMockBuilder(Elasticsearch::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('create')->willReturn($mock);

        $options = [];

        $result = $mock->create($options);

        $this->assertInstanceOf(Elasticsearch::class, $result);
    }
}