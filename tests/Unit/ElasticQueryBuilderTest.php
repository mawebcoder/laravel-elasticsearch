<?php

namespace Tests\Unit;



use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
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
        ],
        '_source' => []
    ];

    protected EUserModel $elastic;

    protected function setUp(): void
    {
        $this->elastic = new EUserModel();
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

        $this->expected['query']['bool']['should'][$this->elastic::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $field => [
                    'gt' => $values[1]
                ]
            ]
        ];

        $this->expected['query']['bool']['should'][]['bool']['must'][] = [
            'range' => [
                $field => [
                    'lt' => $values[0],
                ]
            ]
        ];

        $this->assertEquals($this->expected, $this->elastic->search);
    }

    public function test_elastic_or_where_without_operation(): void
    {
        $field = 'test_field';
        $value = 'value';


        $this->elastic->orWhere($field, $value);

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
                      'term' => [
                          $field =>[
                              'value'=> $value
                          ]
                      ]
                  ]
                    ]
                ]
            ],
            '_source' => []
        ];

        $this->assertEquals($expected, $this->elastic->search);
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

        $this->expected['query']['bool']['should'][]['bool']['must_not'][] = [
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
        $elastic = new EUserModel();

        $size = 10;

        $elastic->take($size);

        $this->assertEquals($size, $elastic->search['size']);
    }

    /**
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function test_elastic_select_method()
    {
        $elastic = new EUserModel();

        $elastic->select('id', 'name', 'value')
            ->select('home', 'car');

        $expectation = [
            '_id',
            'name',
            'value',
            'home',
            'car'
        ];

        $this->assertEquals($expectation, $elastic->search['_source']);
    }


    public function test_parse_field(): void
    {
        $elastic = new EUserModel();

        $field = $elastic->parseField('id');

        $this->assertEquals('_id', $field);
    }

    public function testIsNestedMethodReturnsTrueIfIsNestedString(): void
    {
        $elastic = new EUserModel();

        $isNested = $elastic->isNestedField('mohammad.amiri');

        $this->assertTrue($isNested);
    }

    public function testIsNestedMethodReturnsFalseIfIsNestedString(): void
    {
        $elastic = new EUserModel();

        $isNested = $elastic->isNestedField('mohammad');

        $this->assertFalse($isNested);
    }

    public function testGetNestedFieldsAsArrayMethod(): void
    {
        $elastic = new EUserModel();

        $result = $elastic->getNestedFieldsAsArray('mohammad.amiri');

        $this->assertEquals([
            'mohammad',
            'amiri'
        ], $result);
    }


    /**
     * @return void
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     */
    public function test_encounter_exception_if_select_input_is_no_scalar_type()
    {
        $elastic = new EUserModel();

        $this->expectException(SelectInputsCanNotBeArrayOrObjectException::class);

        $this->expectExceptionMessage('select inputs can not be array or objects.just scalar types are valid');

        $elastic->select(['name']);
    }


    /**
     * @throws InvalidSortDirection
     */
    public function test_order_by_method(): void
    {
        $elastic = new EUserModel();

        $elastic->orderBy('id', 'desc')
            ->orderBy('value');

        $expectation = [
            [
                '_id' => [
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
        $elastic = new EUserModel();

        $this->expectException(InvalidSortDirection::class);

        $this->expectExceptionMessage('sort direction must be either asc or desc.');

        $wrongDirection = 'wrong';

        $elastic->orderBy('id', $wrongDirection);
    }


    public function testWhereNotLike()
    {
        $elasticsearch = new EUserModel();

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
        $elasticsearch = new EUserModel();

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
        $elasticsearch = new EUserModel();

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
        $elasticsearch = new EUserModel();

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

    /**
     * @throws ReflectionException
     */
    public function testGetInClosureQueryForWhereInEqualCondition(): void
    {
        $value = null;
        $operation = "==";
        $field = 'age';

        $reflectionElasticModel = new ReflectionClass(EUserModel::class);

        $object = $reflectionElasticModel->newInstance();

        $reflectionMethod = $reflectionElasticModel->getMethod('getInClosureQueryForWhere');

        $query = $reflectionMethod->invoke($object, $operation, $value, $field);

        $expected =
            [
                'exists' => [
                    'field' => 'age'
                ]
            ];

        $this->assertEquals($expected, $query);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetInClosureQueryForWhereInNotEqualCondition(): void
    {
        $value = null;
        $operation = "<>";
        $field = 'age';

        $reflectionElasticModel = new ReflectionClass(EUserModel::class);

        $object = $reflectionElasticModel->newInstance();

        $reflectionMethod = $reflectionElasticModel->getMethod('getInClosureQueryForWhere');

        $query = $reflectionMethod->invoke($object, $operation, $value, $field);

        $expected = [
            'bool' => [
                'must_not' => [
                    [
                        'exists' => [
                            'field' => 'age'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $query);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetInClosureQueryForWhereInLikeCondition(): void
    {
        $value = null;
        $operation = "like";
        $field = 'age';

        $reflectionElasticModel = new ReflectionClass(EUserModel::class);

        $object = $reflectionElasticModel->newInstance();

        $reflectionMethod = $reflectionElasticModel->getMethod('getInClosureQueryForWhere');

        $query = $reflectionMethod->invoke($object, $operation, $value, $field);

        $expected =
            [
                'exists' => [
                    'field' => 'age'
                ]
            ];

        $this->assertEquals($expected, $query);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetInClosureQueryForWhereInNotLikeCondition(): void
    {

        $value = null;
        $operation = "not like";
        $field = 'age';

        $reflectionElasticModel = new ReflectionClass(EUserModel::class);

        $object = $reflectionElasticModel->newInstance();

        $object->orWhereTerm('ali', '=', null);

        $reflectionMethod = $reflectionElasticModel->getMethod('getInClosureQueryForWhere');

        $query = $reflectionMethod->invoke($object, $operation, $value, $field);

        $expected = [
            'bool' => [
                'must_not' => [
                    [
                        'exists' => [
                            'field' => 'age'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $query);
    }


    public function test_where_term_is_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->whereTerm('id', null);

        $expected = [
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
                                                    'field' => '_id'
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

        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_where_term_is_not_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->whereTerm('id', BaseElasticsearchModel::OPERATOR_NOT_EQUAL, null);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                [
                                    'exists' => [
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]

        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_term_is_not_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhereTerm('id', BaseElasticsearchModel::OPERATOR_NOT_EQUAL, null);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => []
                        ]
                    ],
                    [
                        'exists' => [
                            'field' => '_id'
                        ]
                    ]
                ]
            ]
        ];


        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_term_is_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhereTerm('id', null);

        $expected = [
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
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_where_in_is_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $values = [1, 2, 3, null];

        $elasticsearchModel->whereIn('id', [1, 2, 3, null]);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                [
                                    'terms' => [
                                        '_id' => array_filter($values, static fn($value) => !is_null($value))
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'bool' => [
                            'must_not' => [
                                [
                                    'exists' => [
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_where_not_in_is_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $values = [1, 2, 3, null];

        $elasticsearchModel->whereNotIn('id', [1, 2, 3, null]);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                [
                                    'bool' => [
                                        'must_not' => [
                                            [
                                                'terms' => [
                                                    '_id' => array_filter($values, static fn($value) => !is_null($value)
                                                    )
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    'exists' => [
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhere('id', null);

        $expected = [
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
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_not_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhere('id', BaseElasticsearchModel::OPERATOR_NOT_EQUAL, null);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => []
                        ]
                    ],
                    [
                        'exists' => [
                            'field' => '_id'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_like_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhere('id', BaseElasticsearchModel::OPERATOR_LIKE, null);

        $expected = [
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
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_not_like_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->orWhere('id', BaseElasticsearchModel::OPERATOR_NOT_LIKE, null);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => []
                        ]
                    ],
                    [
                        'exists' => [
                            'field' => '_id'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_in_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $values = [1, 2, 3, null];

        $elasticsearchModel->orWhereIn('id', $values);

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => []
                        ]
                    ],
                    [
                        'terms' => [
                            '_id' => array_filter($values, static fn($value) => !is_null($value))
                        ]
                    ],
                    [
                        'bool' => [
                            'must_not' => [
                                [
                                    'exists' => [
                                        'field' => '_id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    public function test_or_where_not_in_null_query(): void
    {
        $elasticsearchModel = new EUserModel();

        $values = [1, 2, 3, null];

        $elasticsearchModel->orWhereNotIn('id', $values);

        $expected = [
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
                                    'terms' => [
                                        '_id' => array_filter($values, static fn($value) => !is_null($value))
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'exists' => [
                            'field' => '_id'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['query']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_invalid_indices_name_validation(): void
    {
        $index = 'Mohammad';

        $reflection = new ReflectionClass(EUserModel::class);

        $object = $reflection->newInstance();

        $method = $reflection->getMethod('validateIndex');

        $this->withoutExceptionHandling();

        $this->expectException(IndexNamePatternIsNotValidException::class);

        $this->expectExceptionMessage(
            $index . ' index is not a valid indices name.the valid pattern is /^[a-z][a-z0-9_-]$/'
        );

        $method->invoke($object, $index);
    }


    /**
     * @throws JsonException
     */
    public function test_build_script_method_on_nested_array(): void
    {
        $elasticModel = new EUserModel();

        $result = $elasticModel->buildScript([
            'information.data' => [
                'name' => ['age' => 'ali', 'family' => 'amiri'],
                'family' => ['color' => ['status' => 'red']]
            ]
        ]);

        $expected = 'ctx._source.information.data.name.age = ali;ctx._source.information.data.name.family = amiri;ctx._source.information.data.family.color.status = red;';

        $this->assertEquals($expected, $result);
    }

    /**
     * @throws JsonException
     */
    public function test_build_script_method_on_string(): void
    {
        $elasticModel = new EUserModel();

        $result = $elasticModel->buildScript([
            'information.data' => 'jafar'
        ]);

        $expected = 'ctx._source.information.data = jafar;';

        $this->assertEquals($expected, $result);
    }


}
