<?php

namespace Tests\Unit;

use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use PHPUnit\Framework\TestCase;

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
        ],
        'fields' => []
    ];

    protected Elasticsearch $elastic;

    protected function setUp(): void
    {
        $this->elastic = new Elasticsearch();
    }

    public function test_elastic_where_without_operation(): void
    {
        $field = 'test_field';
        $value = null;

        $this->elastic->where($field);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_only_value(): void
    {
        $field = 'test_field';
        $value = 'test';

        $this->elastic->where($field, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_operation_not_equal(): void
    {
        $clone = clone $this->elastic;

        $field = 'test_field';
        $operation1 = '!=';
        $operation2 = '<>';
        $value = 'test_value';

        $this->elastic->where($field, $operation1, $value);
        $clone->where($field, $operation2, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
        $this->assertEquals($this->expected, $clone->search);
    }

    public function test_elastic_where_operation_greater_than(): void
    {
        $field = 'test_field';
        $operation = '>';
        $value = 'test';

        $this->elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "range" => [
                $field => [
                    'gt' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_operation_greater_than_equal(): void
    {
        $field = 'test_field';
        $operation = '>=';
        $value = 'test';

        $this->elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "range" => [
                $field => [
                    'gte' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_operation_less_than(): void
    {
        $field = 'test_field';
        $operation = '<';
        $value = 'test';

        $this->elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "range" => [
                $field => [
                    'lt' => $value
                ]
            ]
        ];


        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_operation_less_than_equal(): void
    {
        $field = 'test_field';
        $operation = '<=';
        $value = 'test';

        $this->elastic->where($field, $operation, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "range" => [
                $field => [
                    'lte' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_term_not_equal(): void
    {
        $clone = clone $this->elastic;

        $field = 'test_field';
        $operation1 = '!=';
        $operation2 = '<>';
        $value = 'test';

        $this->elastic->whereTerm($field, $operation1, $value);
        $clone->whereTerm($field, $operation2, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            "match" => [
                $field => [
                    'query' => $value
                ]
            ]
        ];


        $this->assertEquals($this->expected, $this->elastic->search);
        $this->assertEquals($this->expected, $clone->search);
    }

    public function test_elastic_where_term_equal(): void
    {
        $clone = clone $this->elastic;

        $field = 'test_field';
        $operation = '=';
        $value = 'test';

        $this->elastic->whereTerm($field, $operation, $value);
        $clone->whereTerm($field, $value);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "match" => [
                $field => [
                    'query' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
        $this->assertEquals($this->expected, $clone->search);
    }

    public function test_elastic_where_in(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->elastic->whereIn($field, $values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            "terms" => [
                $field => $values
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_where_not_in(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->elastic->whereNotIn($field, $values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            "terms" => [
                $field => $values
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_between_wrong_value_number(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->whereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_between_one_value(): void
    {
        $field = 'test_field';
        $values = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->whereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function test_elastic_where_between_must_be_numeric(): void
    {
        $field = 'test_field';
        $values = ['foo', 'bar'];

        $this->expectException(WrongArgumentType::class);
        $this->expectExceptionMessage('values must be numeric.');

        $this->elastic->whereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_where_between(): void
    {
        $field = 'test_field';
        $values = [1, 9];

        $this->elastic->whereBetween($field, $values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $field => [
                    'gte' => $values[0],
                    'lte' => $values[1]
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_not_between_wrong_value_number(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->whereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_where_not_between_one_value(): void
    {
        $field = 'test_field';
        $values = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->whereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function test_elastic_where_not_between_must_be_numeric(): void
    {
        $field = 'test_field';
        $values = ['foo', 'bar'];

        $this->expectException(WrongArgumentType::class);
        $this->expectExceptionMessage('values must be numeric.');

        $this->elastic->whereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_where_not_between(): void
    {
        $field = 'test_field';
        $values = [1, 9];

        $this->elastic->whereNotBetween($field, $values);

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'range' => [
                $field => [
                    'lt' => $values[0],
                    'gt' => $values[1]
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_without_operation(): void
    {
        $field = 'test_field';
        $value = null;

        $this->elastic->orWhere($field);

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_only_value(): void
    {
        $field = 'test_field';
        $value = 'test';

        $this->elastic->orWhere($field, $value);

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_equal(): void
    {
        $field = 'test_field';
        $operation = '=';
        $value = 'test';

        $this->elastic->orWhere($field, $operation, $value);

        $this->expected['query']['bool']['should'][] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_operation_not_equal(): void
    {
        $clone = clone $this->elastic;

        $field = 'test_field';
        $operation1 = '!=';
        $operation2 = '<>';
        $value = 'test_value';

        $this->elastic->orWhere($field, $operation1, $value);
        $clone->orWhere($field, $operation2, $value);

        $this->expected['query']['bool']['should'][]['bool']['must_not'] = [
            "term" => [
                $field => [
                    'value' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
        $this->assertEquals($this->expected, $clone->search);
    }

    public function test_elastic_or_where_operation_greater_than(): void
    {
        $field = 'test_field';
        $operation = '>';
        $value = 'test';

        $this->elastic->orWhere($field, $operation, $value);

        $this->expected['query']['bool']['should'][] = [
            "range" => [
                $field => [
                    'gt' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_operation_greater_than_equal(): void
    {
        $field = 'test_field';
        $operation = '>=';
        $value = 'test';

        $this->elastic->orWhere($field, $operation, $value);

        $this->expected['query']['bool']['should'][] = [
            "range" => [
                $field => [
                    'gte' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_operation_less_than(): void
    {
        $field = 'test_field';
        $operation = '<';
        $value = 'test';

        $this->elastic->orWhere($field, $operation, $value);

        $this->expected['query']['bool']['should'][] = [
            "range" => [
                $field => [
                    'lt' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_operation_less_than_equal(): void
    {
        $field = 'test_field';
        $operation = '<=';
        $value = 'test';

        $this->elastic->orWhere($field, $operation, $value);

        $this->expected['query']['bool']['should'][] = [
            "range" => [
                $field => [
                    'lte' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_in(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->elastic->orWhereIn($field, $values);

        $this->expected['query']['bool']['should'][] = [
            "terms" => [
                $field => $values
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_not_in(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->elastic->orWhereNotIn($field, $values);

        $this->expected['query']['bool']['should'][]['bool']['must_not'][] = [
            "terms" => [
                $field => $values
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_or_where_between_wrong_value_number(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->orWhereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_or_where_between_one_value(): void
    {
        $field = 'test_field';
        $values = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->orWhereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function test_elastic_or_where_between_must_be_numeric(): void
    {
        $field = 'test_field';
        $values = ['foo', 'bar'];

        $this->expectException(WrongArgumentType::class);
        $this->expectExceptionMessage('values must be numeric.');

        $this->elastic->orWhereBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_or_where_between(): void
    {
        $field = 'test_field';
        $values = [1, 9];

        $this->elastic->orWhereBetween($field, $values);

        $this->expected['query']['bool']['should'][] = [
            'range' => [
                $field => [
                    'gte' => $values[0],
                    'lte' => $values[1]
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_or_where_not_between_wrong_value_number(): void
    {
        $field = 'test_field';
        $values = ['test', 'bla bla', 'foo bar'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->orWhereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentType
     */
    public function test_elastic_or_where_not_between_one_value(): void
    {
        $field = 'test_field';
        $values = ['test'];

        $this->expectException(WrongArgumentNumberForWhereBetweenException::class);
        $this->expectExceptionMessage('values members must be 2');

        $this->elastic->orWhereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function test_elastic_or_where_not_between_must_be_numeric(): void
    {
        $field = 'test_field';
        $values = ['foo', 'bar'];

        $this->expectException(WrongArgumentType::class);
        $this->expectExceptionMessage('values must be numeric.');

        $this->elastic->orWhereNotBetween($field, $values);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function test_elastic_or_where_not_between(): void
    {
        $field = 'test_field';
        $values = [1, 9];

        $this->elastic->orWhereNotBetween($field, $values);

        $this->expected['query']['bool']['should'][]['bool']['must_not'][] = [
            'range' => [
                $field => [
                    'gt' => $values[1],
                    'lt' => $values[0]
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */

    /**
     * @throws InvalidSortDirection
     */
    public function test_elastic_order_by_default(): void
    {
        $field = 'test_field';

        $this->elastic->orderBy($field);

        $this->expected['sort'][] = [
            $field => [
                'order' => 'asc'
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    /**
     * @throws InvalidSortDirection
     */
    public function test_elastic_order_by(): void
    {
        $clone = clone $this->elastic;

        $field = 'test_field';
        $direction = 'desc';

        $this->elastic->orderBy($field, $direction);

        $this->expected['sort'][] = [
            $field => [
                'order' => $direction
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);

        $direction = 'asc';

        $clone->orderBy($field, $direction);

        $this->expected['sort'][0][$field]['order'] = $direction;

        $this->assertEquals($this->expected, $clone->search);
    }

    public function test_elastic_order_by_invalid(): void
    {
        $field = 'test_field';
        $direction = 'foo';

        $this->expectException(InvalidSortDirection::class);
        $this->expectExceptionMessage('sort direction must be either asc or desc.');

        $this->elastic->orderBy($field, $direction);
    }

    public function test_elastic_take_and_limit(): void
    {
        $clone = clone $this->elastic;

        $limit = 10;

        $this->elastic->take($limit);
        $clone->limit($limit);

        $this->expected['size'] = $limit;

        $this->assertEquals($this->expected, $this->elastic->search);
        $this->assertEquals($this->expected, $clone->search);
    }

    public function test_elastic_offset(): void
    {
        $offset = 5;

        $this->elastic->offset($offset);

        $this->expected['from'] = $offset;

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_refresh_search(): void
    {
        $this->elastic->refreshSearch();

        $this->expected = [];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_take_method()
    {
        $elastic = new Elasticsearch();

        $size = 10;

        $elastic->take($size);

        $this->assertEquals($size, $elastic->search['size']);
    }

    /**
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function test_elastic_select_method()
    {
        $elastic = new Elasticsearch();

        $elastic->select('id', 'name', 'value')
            ->select('home', 'car');

        $expectation = [
            'id',
            'name',
            'value',
            'home',
            'car'
        ];

        $this->assertEquals($expectation, $elastic->search['fields']);
    }

    public function test_encounter_exception_if_select_input_is_no_scalar_type()
    {
        $elastic = new Elasticsearch();

        $this->expectException(SelectInputsCanNotBeArrayOrObjectException::class);

        $this->expectExceptionMessage('select inputs can not be array or objects.just scalar types are valid');

        $elastic->select(['name']);
    }


    /**
     * @throws InvalidSortDirection
     */
    public function test_order_by_method()
    {
        $elastic = new Elasticsearch();

        $elastic->orderBy('id', 'desc')
            ->orderBy('value');

        $expectation = [
            [
                'id' => [
                    'order' => 'desc'
                ]
            ],
            [
                'value' => [
                    'order' => 'asc'
                ]
            ]

        ];

        $this->assertEquals($expectation, $elastic->search['sort']);
    }

    public function test_sort_direction_exception()
    {
        $elastic = new Elasticsearch();

        $this->expectException(InvalidSortDirection::class);

        $this->expectExceptionMessage('sort direction must be either asc or desc.');

        $wrongDirection = 'wrong';

        $elastic->orderBy('id', $wrongDirection);
    }


    public function testWhereNotLike()
    {
        $elasticsearch = new Elasticsearch();

        $field = 'name';

        $value = 'ali nasi';

        $elasticsearch->where($field, 'not like', $value);

        $this->expected['query']['bool']['should']
        [$elasticsearch::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            "match_phrase_prefix" => [
                $field => [
                    'query' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $elasticsearch->search);
    }

    public function testWhereLike()
    {
        $elasticsearch = new Elasticsearch();

        $field = 'name';

        $value = 'ali nasi';

        $elasticsearch->where($field, 'like', $value);

        $this->expected['query']['bool']['should']
        [$elasticsearch::MUST_INDEX]['bool']['must'][] = [
            "match_phrase_prefix" => [
                $field => [
                    'query' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $elasticsearch->search);
    }


    public function testOrWhereNotLike()
    {
        $elasticsearch = new Elasticsearch();

        $field = 'name';

        $value = 'ali nasi';

        $elasticsearch->orWhere($field, 'not like', $value);

        $this->expected['query']['bool']['should']
        []['bool']['must_not'][] = [
            "match_phrase_prefix" => [
                $field => [
                    'query' => $value
                ]
            ]
        ];


        $this->assertEquals($this->expected, $elasticsearch->search);
    }

    public function testOrWhereLike()
    {
        $elasticsearch = new Elasticsearch();

        $field = 'name';

        $value = 'ali nasi';

        $elasticsearch->orWhere($field, 'like', $value);

        $this->expected['query']['bool']['should'][]
        = [
            "match_phrase_prefix" => [
                $field => [
                    'query' => $value
                ]
            ]
        ];

        $this->assertEquals($this->expected, $elasticsearch->search);
    }
}
