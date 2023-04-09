<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;

class ElasticChainingQueryBuilderTest extends TestCase
{
    public array $expected = [
        "query" => [
            "bool" => [
                "should" => [
                    0 => [
                        "bool" => [
                            "must" => [

                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    public function test_elastic_chaining_where_or_where(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';

        $elastic->where($field, $value)->orWhere($field, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_or_where_before_where(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';

        $elastic->orWhere($field, $value)->where($field, $value);

        $this->expected['query']['bool']['should'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }
}