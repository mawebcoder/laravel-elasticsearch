<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use ReflectionException;
use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Tests\TestCase\Integration\BaseIntegrationTestCase;
use Throwable;

/**
 * @depends MigrationTest
 */
class QueryBuilderTest extends BaseIntegrationTestCase
{


    public function testFindMethod()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 2,
            EUserModel::KEY_NAME => 'mamad',
            EUserModel::KEY_AGE => 23,
            EUserModel::KEY_IS_ACTIVE => true,
            EUserModel::KEY_DESCRIPTION => 'dummy description'
        ];

        $user = new EUserModel();

        $this->insertElasticDocument($user, $data);

        $record = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals($data, $record->getAttributes());
    }


    public function testSetNullForUndefinedMappedData()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 1,
            EUserModel::KEY_DESCRIPTION => 'dummy description'
        ];

        $userModel = new EUserModel();

        $this->insertElasticDocument($userModel, $data);

        $result = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals(
            [
                BaseElasticsearchModel::KEY_ID => 1,
                EUserModel::KEY_DESCRIPTION => $data[EUserModel::KEY_DESCRIPTION],
                EUserModel::KEY_NAME => null,
                EUserModel::KEY_AGE => null,
                EUserModel::KEY_IS_ACTIVE => null,
            ],
            $result->attributes
        );
    }


    public function testCanUpdateData()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 1,
            EUserModel::KEY_IS_ACTIVE => false,
            EUserModel::KEY_NAME => 'mamad',
            EUserModel::KEY_DESCRIPTION => 'dummy description'
        ];

        $this->insertElasticDocument(new EUserModel(), $data);

        $model = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $newData = [
            EUserModel::KEY_NAME => $this->faker->unique()->name,
            EUserModel::KEY_IS_ACTIVE => true,
            EUserModel::KEY_DESCRIPTION => 'dummy description'
        ];

        $this->update($model, $newData);

        $model = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $expectation = [
            BaseElasticsearchModel::KEY_ID => $data[BaseElasticsearchModel::KEY_ID],
            EUserModel::KEY_NAME => $newData[EUserModel::KEY_NAME],
            EUserModel::KEY_IS_ACTIVE => true,
            EUserModel::KEY_DESCRIPTION => $newData[EUserModel::KEY_DESCRIPTION],
            EUserModel::KEY_AGE => null
        ];

        $this->assertEquals($expectation, $model->getAttributes());
    }


    public function testCanDeleteDataByModelRecord()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 1,
            EUserModel::KEY_DESCRIPTION => 'dummy description'
        ];

        $this->insertElasticDocument(new EUserModel(), $data);

        $model = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $model->mustBeSync()->delete();

        $model = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals(null, $model);
    }


    public function testSelect()
    {
        $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->select(EUserModel::KEY_NAME, BaseElasticsearchModel::KEY_ID)
            ->get();

        $firstResultAttributes = $results->first()->getAttributes();

        $this->assertEquals([EUserModel::KEY_NAME, BaseElasticsearchModel::KEY_ID],
            array_keys($firstResultAttributes));
    }


    public function testTake()
    {
        $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->take(1)
            ->get();

        $this->assertEquals(1, $results->count());
    }


    public function testOffset()
    {
        $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->offset(1)
            ->get();

        $this->assertCount(2, $results);
    }


    public function testWhereEqualCondition()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, $data[0]->{EUserModel::KEY_NAME})
            ->select(EUserModel::KEY_NAME)
            ->get();

        $this->assertEquals(1, $results->count());

        $firstResult = $results->first()->attributes[EUserModel::KEY_NAME];

        $this->assertEquals($data[0]->{EUserModel::KEY_NAME}, $firstResult);
    }


    public function testWhereNotEqualCondition()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, '<>', $data[0]->{EUserModel::KEY_NAME})
            ->select(EUserModel::KEY_NAME)
            ->get();

        $this->assertEquals(2, $results->count());
    }


    public function testOrWhereCondition()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, $data[0]->{EUserModel::KEY_NAME})
            ->orWhere(EUserModel::KEY_AGE, $data[1]->{EUserModel::KEY_AGE})
            ->select(EUserModel::KEY_NAME, EUserModel::KEY_AGE)
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_NAME} == $data[0]->{EUserModel::KEY_NAME})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} == $data[1]->{EUserModel::KEY_AGE})
        );
    }


    public function testNotBetweenCondition()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->whereNotBetween(
                EUserModel::KEY_AGE,
                [$data[0]->{EUserModel::KEY_AGE}, $data[1]->{EUserModel::KEY_AGE}]
            )->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === $data[2]->{EUserModel::KEY_AGE}));
    }


    public function testOrBetweenCondition()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, $data[1]->{EUserModel::KEY_AGE})
            ->orWhereBetween(
                EUserModel::KEY_AGE,
                [$data[0]->{EUserModel::KEY_AGE}, $data[2]->{EUserModel::KEY_AGE}]
            )->get();

        $this->assertEquals(3, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => intval($row->{EUserModel::KEY_AGE}) === $data[0]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => intval($row->{EUserModel::KEY_AGE}) === $data[1]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => intval($row->{EUserModel::KEY_AGE}) === $data[2]->{EUserModel::KEY_AGE})
        );
    }


    public function testOrderByAsc()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->orderBy(EUserModel::KEY_AGE)
            ->get();

        $first = $results->first();

        $second = $results[1];

        $third = $results[2];

        $this->assertEquals($data[0]->{EUserModel::KEY_AGE}, $first->{EUserModel::KEY_AGE});

        $this->assertEquals($data[1]->{EUserModel::KEY_AGE}, $second->{EUserModel::KEY_AGE});

        $this->assertEquals($data[2]->{EUserModel::KEY_AGE}, $third->{EUserModel::KEY_AGE});
    }


    public function testOrderByDesc()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->orderBy(EUserModel::KEY_AGE, 'desc')
            ->get();

        $first = $results->first();

        $second = $results[1];

        $third = $results[2];

        $this->assertEquals($data[2]->{EUserModel::KEY_AGE}, $first->{EUserModel::KEY_AGE});

        $this->assertEquals($data[1]->{EUserModel::KEY_AGE}, $second->{EUserModel::KEY_AGE});

        $this->assertEquals($data[0]->{EUserModel::KEY_AGE}, $third->{EUserModel::KEY_AGE});
    }


    public function testWhereTerm()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->whereTerm(EUserModel::KEY_NAME, $data[0]->{EUserModel::KEY_NAME})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_NAME} === $data[0]->{EUserModel::KEY_NAME})
        );
    }


    public function testOrWhereTerm()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, $data[0]->{EUserModel::KEY_NAME})
            ->orWhereTerm(EUserModel::KEY_NAME, $data[1]->{EUserModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_NAME} === $data[0]->{EUserModel::KEY_NAME})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_NAME} === $data[1]->{EUserModel::KEY_NAME})
        );
    }


    public function testWhereNotTerm()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->whereTerm(EUserModel::KEY_NAME, '<>', $data[0]->{EUserModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->{EUserModel::KEY_NAME}));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[2]->{EUserModel::KEY_NAME}));
    }


    public function testOrWhereEqual()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, $data[0]->{EUserModel::KEY_NAME})
            ->orWhere(EUserModel::KEY_NAME, $data[1]->{EUserModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[0]->{EUserModel::KEY_NAME}));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->{EUserModel::KEY_NAME}));
    }


    public function testWhereGreaterThan()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, '>', $data[0]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertCount(2, $results);

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[1]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[2]->{EUserModel::KEY_AGE})
        );
    }


    public function testOrWhereGreaterThan()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, $data[0]->{EUserModel::KEY_AGE})
            ->orWhere(EUserModel::KEY_AGE, '>', $data[1]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[2]->{EUserModel::KEY_AGE})
        );
    }


    public function testWhereLessThan()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, '<', $data[1]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->age === $data[0]->{EUserModel::KEY_AGE}));
    }


    public function testOrWhereLessThan()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, $data[2]->{EUserModel::KEY_AGE})
            ->orWhere(EUserModel::KEY_AGE, '<', $data[1]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[2]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[0]->{EUserModel::KEY_AGE})
        );
    }


    public function testWhereLike()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_DESCRIPTION, 'like', $data[0]->{EUserModel::KEY_DESCRIPTION})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{EUserModel::KEY_DESCRIPTION} === $data[0]->{EUserModel::KEY_DESCRIPTION}
            )
        );
    }


    public function testWhereNotLike()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_DESCRIPTION, 'not like', $data[0]->{EUserModel::KEY_DESCRIPTION})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{EUserModel::KEY_DESCRIPTION} === $data[1]->{EUserModel::KEY_DESCRIPTION}
            )
        );
        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{EUserModel::KEY_DESCRIPTION} === $data[2]->{EUserModel::KEY_DESCRIPTION}
            )
        );
    }


    public function testOrWhereLike()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, $data[0]->{EUserModel::KEY_AGE})
            ->orWhere(EUserModel::KEY_DESCRIPTION, 'like', $data[1]->{EUserModel::KEY_DESCRIPTION})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[0]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{EUserModel::KEY_DESCRIPTION} === $data[1]->{EUserModel::KEY_DESCRIPTION}
            )
        );
    }


    public function testWhereGreaterThanOrEqual()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, '>=', $data[1]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[2]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[1]->{EUserModel::KEY_AGE})
        );
    }


    public function testOrWhereGreaterThanOrEqual()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, $data[0]->{EUserModel::KEY_AGE})
            ->orWhere(EUserModel::KEY_AGE, '>=', $data[1]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(3, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[0]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[1]->{EUserModel::KEY_AGE})
        );
        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[2]->{EUserModel::KEY_AGE})
        );
    }


    public function testWhereLessThanOrEqual()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, '<=', $data[0]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[0]->{EUserModel::KEY_AGE})
        );
    }


    public function testOrWhereLessThanOrEqual()
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, $data[2]->{EUserModel::KEY_AGE})
            ->orWhere(EUserModel::KEY_AGE, '<=', $data[0]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[0]->{EUserModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[2]->{EUserModel::KEY_AGE})
        );
    }


    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testGetMappings(): void
    {
        $mappings = EUserModel::newQuery()->getMappings();

        $expected = [
            EUserModel::KEY_AGE => [
                'type' => 'integer'
            ],
            EUserModel::KEY_IS_ACTIVE => [
                "type" => "boolean"
            ],
            EUserModel::KEY_NAME => [
                "type" => "keyword"
            ],
            EUserModel::KEY_DESCRIPTION => [
                "type" => "text"
            ]
        ];

        $this->assertEquals($expected, $mappings);
    }

    /**
     * @throws Throwable
     */
    public function test_where_clause_while_value_is_null(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->where('id', null);

        $this->assertEquals([
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
                            ],
                        ]
                    ]
                ]
            ]

        ], $elasticsearchModel->search['query']);
    }

    /**
     * @throws Throwable
     */
    public function test_where_clause_while_value_is_not_null(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->where('id', BaseElasticsearchModel::OPERATOR_NOT_EQUAL_SPACESHIP, null);

        $this->assertEquals([
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
                            ],
                        ]
                    ]
                ]
            ]

        ], $elasticsearchModel->search['query']);
    }

    /**
     * @throws Throwable
     */
    public function test_where_clause_while_value_is_like_null(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->where('id', BaseElasticsearchModel::OPERATOR_LIKE, null);

        $this->assertEquals([
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
                            ],
                        ]
                    ]
                ]
            ]

        ], $elasticsearchModel->search['query']);
    }

    /**
     * @throws Throwable
     */
    public function test_where_clause_while_value_is_not_like_null(): void
    {
        $elasticsearchModel = new EUserModel();

        $elasticsearchModel->where('id', BaseElasticsearchModel::OPERATOR_NOT_LIKE, null);

        $this->assertEquals([
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
                            ],
                        ]
                    ]
                ]
            ]

        ], $elasticsearchModel->search['query']);
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

    public function test_where_not_in_is_null_query():void
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
                                    'exists'=>[
                                        'field'=>'_id'
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


}