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

    public string $field        = 'test_field';
    public string $value        = 'test';
    public string $dateField    = 'test_date';
    public array $betweenValues = [1, 9];
    public string $otherField   = 'test_id';
    public array $exceptions    = ['foo', 'bar', 'xyz'];
    public array $values        = ['foo', 'bar', 'xyz'];

    public function test_elastic_chaining_where_or_where(): void
    {
        $elastic = new Elasticsearch();

        $elastic->where($this->field, $this->value)->orWhere($this->field, $this->value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
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

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_or_where_before_where(): void
    {
        $elastic = new Elasticsearch();

        $elastic->orWhere($this->field, $this->value)->where($this->field, $this->value);

        $this->expected['query']['bool']['should'][] = [
                    "term" => [
                        $this->field => [
                            'value' => $this->value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $this->field => [
                            'value' => $this->value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_or_where_term(): void
    {
        $elastic = new Elasticsearch();

        $elastic->where($this->field, $this->value)->orWhereTerm($this->field, $this->value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
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

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_term_or_where(): void
    {
        $elastic = new Elasticsearch();

        $operation = '<>';

        $elastic->whereTerm($this->field, $operation, $this->value)->orWhere($this->field, $this->value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
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

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_term_and_where_in(): void
    {
        $elastic = new Elasticsearch();

        $elastic->whereTerm($this->field, $this->value)->whereIn($this->otherField, $this->values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $this->field => [
                            'query' => $this->value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $this->otherField => $this->values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_term_and_where_not_in(): void
    {
        $elastic = new Elasticsearch();

        $elastic->whereTerm($this->field, $this->value)->whereNotIn($this->otherField, $this->values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $this->field => [
                            'query' => $this->value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_chaining_where_and_where_not_in(): void
    {
        $elastic = new Elasticsearch();

        $elastic->where($this->field, $this->value)->whereNotIn($this->otherField, $this->values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $this->field => [
                            'value' => $this->value
                        ]
                    ]
                ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->values
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

        $elastic->whereIn($this->field, $this->values)->whereNotBetween($this->dateField, $this->betweenValues);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $this->field => $this->values
            ]
        ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'range' => [
                $this->dateField => [
                    'lt' => $this->betweenValues[0],
                    'gt' => $this->betweenValues[1]
                ]
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_chaining_where_in_or_where_between_or_where(): void
    {
        $elastic = new Elasticsearch();

        $elastic->whereIn($this->field, $this->values)
            ->orWhereBetween($this->dateField, $this->betweenValues)
            ->orWhere($this->otherField, $this->value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $this->field => $this->values
            ]
        ];

        $this->expected['query']['bool']['should'][] = [
            'range' => [
                $this->dateField => [
                    'lt' => $this->betweenValues[0],
                    'gt' => $this->betweenValues[1]
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

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_chaining_where_not_in_and_where_not_between_and_where(): void
    {
        $elastic = new Elasticsearch();

        $elastic->whereNotIn($this->field, $this->values)
            ->orWhereNotBetween($this->dateField, $this->betweenValues)
            ->where($this->otherField, $this->value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->exceptions
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

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $this->field => [
                            'value' => $this->value
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

        $elastic->where($this->field, $this->value)
            ->orWhereTerm($this->field, $this->value)
            ->whereBetween($this->dateField, $this->betweenValues)
            ->whereNotIn($this->otherField, $this->exceptions);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
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

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $this->field => [
                    'gte' => $this->betweenValues[0],
                    'lte' => $this->betweenValues[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $this->otherField => $this->exceptions
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }
}