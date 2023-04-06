<?php

namespace Tests;

use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use PHPUnit\Framework\TestCase;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;

class ElasticQueryBuilderTest extends TestCase
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

    public function test_elastic_where_without_operation(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = null;

        $elastic->where($field);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_only_value(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = 'test';

        $elastic->where($field, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_operation_not_equal(): void
    {
        $elastic = new Elasticsearch();

        $clone = clone $elastic;

        $field = 'test_field';
        $operation1 = '!=';
        $operation2 = '<>';
        $value = 'test_value';

        $elastic->where($field, $operation1, $value);
        $clone->where($field, $operation2, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
        $this->assertEqualsCanonicalizing($this->expected, $clone->search);
    }

    public function test_elastic_where_operation_greater_than(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $operation = '>';
        $value = 'test';

        $elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "range" => [
                        $field => [
                            'gt' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_operation_greater_than_equal(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $operation = '>=';
        $value = 'test';

        $elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "range" => [
                        $field => [
                            'gte' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_operation_less_than(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $operation = '>';
        $value = 'test';

        $elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "range" => [
                        $field => [
                            'lt' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_operation_less_than_equal(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $operation = '<=';
        $value = 'test';

        $elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "range" => [
                        $field => [
                            'lte' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_term_not_equal(): void
    {
        $elastic = new Elasticsearch();

        $clone = clone $elastic;

        $field = 'test_field';
        $operation1 = '!=';
        $operation2 = '<>';
        $value = 'test';

        $elastic->whereTerm($field, $operation1, $value);
        $clone->whereTerm($field, $operation2, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
        $this->assertEqualsCanonicalizing($this->expected, $clone->search);
    }

    public function test_elastic_where_term_equal(): void
    {
        $elastic = new Elasticsearch();

        $clone = clone $elastic;

        $field = 'test_field';
        $operation = '=';
        $value = 'test';

        $elastic->whereTerm($field, $operation, $value);
        $clone->whereTerm($field, $value);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
        $this->assertEqualsCanonicalizing($this->expected, $clone->search);
    }

    public function test_elastic_where_in(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $elastic->whereIn($field, $values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            "terms" => [
                $field => $values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_where_not_in(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $elastic->whereNotIn($field, $values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            "terms" => [
                $field => $values
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_between_wrong_value_number(): void
    {
        $elastic = new Elasticsearch();
        $clone = clone $elastic;

        $field = 'test_field';
        $values1 = ['test', 'bla bla', 'foo bar'];
        $values2 = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);

        $elastic->whereBetween($field, $values1);
        $clone->whereBetween($field, $values2);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_between_one_value(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);

        $elastic->whereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function test_elastic_where_between_must_be_numeric(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['foo', 'bar'];

        $this->expectException(WrongArgumentType::class);

        $elastic->whereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_where_between(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = [1, 9];

        $elastic->whereBetween($field, $values);

        $expected = [];

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $field => [
                    'gte' => $values[0],
                    'lte' => $values[1]
                ]
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_not_between_wrong_value_number(): void
    {
        $elastic = new Elasticsearch();
        $clone = clone $elastic;

        $field = 'test_field';
        $values1 = ['test', 'bla bla', 'foo bar'];
        $values2 = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);

        $elastic->whereNotBetween($field, $values1);
        $clone->whereNotBetween($field, $values2);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_not_between_one_value(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);

        $elastic->whereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function test_elastic_where_not_between_must_be_numeric(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = ['foo', 'bar'];

        $this->expectException(WrongArgumentType::class);

        $elastic->whereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_where_not_between(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $values = [1, 9];

        $elastic->whereNotBetween($field, $values);

        $this->expected['query']['bool']['should'][$elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'range' => [
                $field => [
                    'gt' => $values[0],
                    'lt' => $values[1]
                ]
            ]
        ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

    public function test_elastic_or_where_without_operation(): void
    {
        $elastic = new Elasticsearch();

        $field = 'test_field';
        $value = null;

        $elastic->orWhere($field);

        $this->expected['query']['bool']['should'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];

        $this->assertEqualsCanonicalizing($this->expected, $elastic->search);
    }

}
