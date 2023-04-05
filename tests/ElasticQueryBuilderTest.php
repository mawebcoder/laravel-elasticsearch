<?php

namespace Tests;

use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use PHPUnit\Framework\TestCase;

class ElasticQueryBuilderTest extends TestCase
{
    public function test_elastic_where_without_operation()
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = null;

        $elastic->where($field);

        $expected = [];

        $expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($expected, $elastic->search);
    }
}
