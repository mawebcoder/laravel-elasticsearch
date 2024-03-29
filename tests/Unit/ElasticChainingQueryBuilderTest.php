<?php

namespace Tests\Unit;


use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use PHPUnit\Framework\TestCase;
use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;

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
        ],
        '_source' => []
    ];

    public string $field = 'test_field';
    public string $value = 'test';
    public string $dateField = 'test_date';
    public array $betweenValues = [1, 9];
    public string $otherField = 'test_id';
    public array $exceptions = ['foo', 'bar', 'xyz'];
    public array $values = ['foo', 'bar', 'xyz'];

    protected EUserModel $elastic;

    protected function setUp(): void
    {
        $this->elastic = new EUserModel();
    }

    public function test_elastic_chaining_where_or_where(): void
    {
        $this->elastic->where($this->field, $this->value)->orWhere($this->field, $this->value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_chaining_or_where_before_where(): void
    {
        $this->elastic->orWhere($this->field, $this->value)->where($this->field, $this->value);

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_chaining_where_or_where_term(): void
    {
        $this->elastic->where($this->field, $this->value)->orWhereTerm($this->field, $this->value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            "match" => [
                $this->field => [
                    'query' => $this->value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_chaining_where_term_or_where(): void
    {
        $operation = '<>';

        $this->elastic->whereTerm($this->field, $operation, $this->value)->orWhere($this->field, $this->value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            "match" => [
                $this->field => [
                    'query' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_chaining_where_term_and_where_in(): void
    {
        $this->elastic->whereTerm($this->field, $this->value)->whereIn($this->otherField, $this->values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "match" => [
                $this->field => [
                    'query' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $this->otherField => $this->values
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_chaining_where_term_and_where_not_in(): void
    {
        $this->elastic->whereTerm($this->field, $this->value)->whereNotIn($this->otherField, $this->values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "match" => [
                $this->field => [
                    'query' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->values
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_chaining_where_and_where_not_in(): void
    {
        $this->elastic->where($this->field, $this->value)->whereNotIn($this->otherField, $this->values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_where_in_where_not_between(): void
    {
        $this->elastic->whereIn($this->field, $this->values)->whereNotBetween($this->dateField, $this->betweenValues);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $this->field => $this->values
            ]
        ];


        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $this->dateField => [
                    'gt' => $this->betweenValues[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][]['bool']['must'][] = [
            'range' => [
                $this->dateField => [
                    'lt' => $this->betweenValues[0],
                ]
            ]
        ];
        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_chaining_where_in_or_where_between_or_where(): void
    {
        $this->elastic->whereIn($this->field, $this->values)
            ->orWhereBetween($this->dateField, $this->betweenValues)
            ->orWhere($this->otherField, $this->value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $this->field => $this->values
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            'range' => [
                $this->dateField => [
                    'gte' => $this->betweenValues[0],
                    'lte' => $this->betweenValues[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $this->otherField => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_chaining_where_not_in_and_where_not_between_and_where(): void
    {
        $this->elastic->whereNotIn($this->field, $this->values)
            ->orWhereNotBetween($this->dateField, $this->betweenValues)
            ->where($this->otherField, $this->value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->field => $this->values
            ]
        ];


        $this->expected['query']['bool']['should'][]['bool']['must_not'][] = [
            'range' => [
                $this->dateField => [
                    'lt' => $this->betweenValues[0],
                    'gt' => $this->betweenValues[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $this->otherField => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_chaining_where_or_where_term_and_where_between_where_not_in(): void
    {
        $this->elastic->where($this->field, $this->value)
            ->orWhereTerm($this->field, $this->value)
            ->whereBetween($this->dateField, $this->betweenValues)
            ->whereNotIn($this->otherField, $this->exceptions);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $this->field => [
                    'value' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            "match" => [
                $this->field => [
                    'query' => $this->value
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $this->dateField => [
                    'gte' => $this->betweenValues[0],
                    'lte' => $this->betweenValues[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->exceptions
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_where_null_method(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->whereNull('name');

        $expected = [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'bool' => [
                                            'must_not' => [
                                                [
                                                    'exists' => [
                                                        'field' => 'name'
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]

                        ]
                    ]
                ]
            ],
            '_source' => []
        ];

        $this->assertEquals($expected, $elasticsearchModel->search);
    }

    public function test_where_not_null_method(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->whereNotNull('name');

        $expected = [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'exists' => [
                                            'field' => 'name'
                                        ]
                                    ]
                                ]
                            ]

                        ]
                    ]
                ]
            ],
            '_source' => []
        ];

        $this->assertEquals($expected, $elasticsearchModel->search);
    }

    public function test_or_where_null_method(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhereNull('name');

        $expected = [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' => [
                                'must' => []
                            ]
                        ],
                        [
                            'bool' => [
                                'must_not' => [
                                    [
                                        'exists' => [
                                            'field' => 'name'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '_source' => []
        ];

        $this->assertEquals($expected, $elasticsearchModel->search);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     */
    public function testPrefixIndex(): void
    {
        $elasticsearch = new EUserModel();

        $index = $elasticsearch->getIndexWithPrefix();

        $this->assertEquals(config('elasticsearch.index_prefix') . $elasticsearch->getIndex(), $index);
    }

    /**
     * @throws InvalidSortDirection
     */
    public function test_id_on_order_by(): void
    {
        $elasticModel = new EUserModel();

        $elasticModel->orderBy('id', 'desc');

        $this->assertEquals(
            [
                [
                    '_id' => [
                        'order' => 'desc'
                    ]
                ]
            ]
            , $elasticModel->search['sort']
        );
    }

    public function test_or_where_not_null_method(): void
    {

        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhereNotNull('name');

        $expected = [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' => [
                                'must' => []
                            ]
                        ],
                        [
                            'exists' => [
                                'field' => 'name'
                            ]
                        ]
                    ]
                ]
            ],
            '_source' => []
        ];

        $this->assertEquals($expected, $elasticsearchModel->search);
    }
}