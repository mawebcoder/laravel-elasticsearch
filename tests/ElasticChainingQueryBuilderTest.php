<?php

namespace Tests;

use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
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

    public function test_elastic_chaining_where_or_where_term(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';

        $elastic->where($field, $value)->orWhereTerm($field, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_term_or_where(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';
        $operation = '<>';

        $elastic->whereTerm($field, $operation, $value)->orWhere($field, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
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

    public function test_elastic_chaining_where_term_and_where_in(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';
        $otherField = 'test_id';
        $values = ['foo', 'bar', 'xyz'];

        $elastic->whereTerm($field, $value)->whereIn($otherField, $values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $otherField => $values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_term_and_where_not_in(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';
        $otherField = 'test_id';
        $values = ['foo', 'bar', 'xyz'];

        $elastic->whereTerm($field, $value)->whereNotIn($otherField, $values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $otherField => $values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_and_where_not_in(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';
        $otherField = 'test_id';
        $values = ['foo', 'bar', 'xyz'];

        $elastic->where($field, $value)->whereNotIn($otherField, $values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $otherField => $values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_where_in_where_not_between(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['foo', 'bar', 'xyz'];
        $dateField = 'test_date';
        $betweenValues = [1, 9];

        $elastic->whereIn($field, $values)->whereNotBetween($dateField, $betweenValues);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'range' => [
                $dateField => [
                    'lt' => $betweenValues[0],
                    'gt' => $betweenValues[1]
                ]
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_chaining_where_or_where_term_and_where_between_where_not_in(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';
        $dateField = 'test_date';
        $betweenValues = [1, 9];
        $otherField = 'test_id';
        $exceptions = ['foo', 'bar', 'xyz'];

        $elastic->where($field, $value)
            ->orWhereTerm($field, $value)
            ->whereBetween($dateField, $betweenValues)
            ->whereNotIn($otherField, $exceptions);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $field => [
                    'gte' => $betweenValues[0],
                    'lte' => $betweenValues[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $otherField => $exceptions
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }
}