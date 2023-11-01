<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use ReflectionClass;
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testFindMethod(): void
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 2,
            EUserModel::KEY_NAME => 'mamad',
            EUserModel::KEY_AGE => 23,
            EUserModel::KEY_INFORMATION => null,
            EUserModel::KEY_IS_ACTIVE => true,
            EUserModel::KEY_DESCRIPTION => 'dummy description'
        ];

        $user = new EUserModel();

        $this->insertElasticDocument($user, $data);

        $record = EUserModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals($data, $record->getAttributes());
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testSetNullForUndefinedMappedData(): void
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
                EUserModel::KEY_AGE => null,
                EUserModel::KEY_INFORMATION => null,
                EUserModel::KEY_IS_ACTIVE => null,
                EUserModel::KEY_NAME => null,
            ],
            $result->attributes
        );
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testCanUpdateData(): void
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
            EUserModel::KEY_IS_ACTIVE => true,
            EUserModel::KEY_NAME => $newData[EUserModel::KEY_NAME],
            EUserModel::KEY_DESCRIPTION => $newData[EUserModel::KEY_DESCRIPTION],
            EUserModel::KEY_AGE => null,
            EUserModel::KEY_INFORMATION => null,
        ];

        $this->assertEquals($expectation, $model->getAttributes());
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testCanDeleteDataByModelRecord(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws ReflectionException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testSelect(): void
    {
        $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->select(EUserModel::KEY_NAME, BaseElasticsearchModel::KEY_ID)
            ->get();

        $firstResultAttributes = $results->first()->getAttributes();

        $this->assertEquals([EUserModel::KEY_NAME, BaseElasticsearchModel::KEY_ID],
            array_keys($firstResultAttributes));
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testTake(): void
    {
        $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->take(1)
            ->get();

        $this->assertEquals(1, $results->count());
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOffset(): void
    {
        $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->offset(1)
            ->get();

        $this->assertCount(2, $results);
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws JsonException
     * @throws Throwable
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testWhereEqualCondition(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws JsonException
     * @throws Throwable
     * @throws ReflectionException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws GuzzleException
     */
    public function testWhereNotEqualCondition(): void
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, '<>', $data[0]->{EUserModel::KEY_NAME})
            ->select(EUserModel::KEY_NAME)
            ->get();

        $this->assertEquals(2, $results->count());
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws JsonException
     * @throws Throwable
     * @throws ReflectionException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws GuzzleException
     */
    public function testOrWhereCondition(): void
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_NAME, $data[0]->{EUserModel::KEY_NAME})
            ->orWhere(EUserModel::KEY_AGE, $data[1]->{EUserModel::KEY_AGE})
            ->select(EUserModel::KEY_NAME, EUserModel::KEY_AGE)
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_NAME} === $data[0]->{EUserModel::KEY_NAME})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{EUserModel::KEY_AGE} === $data[1]->{EUserModel::KEY_AGE})
        );
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws WrongArgumentType
     * @throws JsonException
     */
    public function testNotBetweenCondition(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws WrongArgumentType
     * @throws JsonException
     * @throws Throwable
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testOrBetweenCondition(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws InvalidSortDirection
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrderByAsc(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws InvalidSortDirection
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrderByDesc(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereTerm(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereTerm(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereNotTerm(): void
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->whereTerm(EUserModel::KEY_NAME, '<>', $data[0]->{EUserModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->{EUserModel::KEY_NAME}));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[2]->{EUserModel::KEY_NAME}));
    }


    /**
     * @throws Throwable
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereEqual(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereGreaterThan(): void
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


    /**
     * @throws Throwable
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereGreaterThan(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereLessThan(): void
    {
        $data = $this->registerSomeTestUserRecords();

        $results = EUserModel::newQuery()
            ->where(EUserModel::KEY_AGE, '<', $data[1]->{EUserModel::KEY_AGE})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->age === $data[0]->{EUserModel::KEY_AGE}));
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereLessThan(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereLike(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereNotLike(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereLike(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereGreaterThanOrEqual(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereGreaterThanOrEqual(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testWhereLessThanOrEqual(): void
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws Throwable
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testOrWhereLessThanOrEqual(): void
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
     * @return void
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function testGetMappings(): void
    {
        $mappings = EUserModel::newQuery()->getMappings();

        $expected = [
            EUserModel::KEY_AGE => [
                'type' => BaseElasticMigration::TYPE_INTEGER
            ],
            EUserModel::KEY_IS_ACTIVE => [
                "type" => BaseElasticMigration::TYPE_BOOLEAN
            ],
            EUserModel::KEY_NAME => [
                "type" => BaseElasticMigration::TYPE_STRING
            ],
            EUserModel::KEY_DESCRIPTION => [
                "type" => BaseElasticMigration::TYPE_TEXT
            ],
            EUserModel::KEY_INFORMATION => [
                'properties' => [
                    'age' => [
                        'type' => BaseElasticMigration::TYPE_INTEGER
                    ],
                    'ages' => [
                        'type' => BaseElasticMigration::TYPE_OBJECT
                    ],
                    'name' => [
                        'type' => BaseElasticMigration::TYPE_STRING
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $mappings);
    }

    /**
     * @throws Throwable
     */
    public function test_where_clause_while_value_is_null(): void
    {
        $this->markTestSkipped();

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
        $this->markTestSkipped();
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
        $this->markTestSkipped();

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
        $this->markTestSkipped();

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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function test_unique_function_in_model(): void
    {

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->unique('name');

        $expected = [
            'field' => 'name'
        ];

        $this->assertEquals($expected, $elasticsearchModel->search['collapse']);
    }

    /** @return void
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException
     * @throws InvalidSortDirection
     */
    public function test_unique_query(): void
    {

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 3;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $result = EUserModel::newQuery()
            ->orderBy('age')
            ->unique(EUserModel::KEY_AGE);

        $expected = [
            [
                'age' => 22,
            ],
            [
                'age' => 45
            ]
        ];

        $ages = [];

        foreach ($result as $value) {

            $ages[] = [
                'age' => $value->age
            ];
        }

        $this->assertEquals($expected, $ages);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws DirectoryNotFoundException
     * @throws JsonException
     */
    public function test_group_by_method_without_sort_field(): void
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->groupBy('age');

        $expected = [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' =>
                                [
                                    'must' => []
                                ]
                        ]
                    ]
                ]
            ],
            '_source' => [],
            'collapse' => [
                'field' => 'age',
                'inner_hits' => [
                    'name' => 'groupBy_age'
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws DirectoryNotFoundException
     * @throws JsonException
     */
    public function test_group_by_method_with_sort_field(): void
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->groupBy('age', 'age', 'desc');

        $expected = [
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' =>
                                [
                                    'must' => []
                                ]
                        ]
                    ]
                ]
            ],
            '_source' => [],
            'collapse' => [
                'field' => 'age',
                'inner_hits' => [
                    'name' => 'groupBy_age',
                    'sort' => [
                        [
                            'age' => [
                                'order' => 'desc'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $elasticsearchModel->search);
    }

    /**
     * @throws RequestException
     * @throws DirectoryNotFoundException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function test_group_by_method_with_sort_field_fetch_data(): void
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 4;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 3;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $result = EUserModel::newQuery()
            ->groupBy('age');

        $expected = [
            22 => 2,
            45 => 2
        ];

        $count = [];

        $result->each(function (array $value, $key) use (&$count) {
            array_walk($value, function () use (&$count, $key) {
                if (!isset($count[$key])) {
                    $count[$key] = 1;
                } else {
                    $count[$key]++;
                }
            });
        });

        $this->assertEquals($expected, $count);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function test_where_null_result(): void
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $results = EUserModel::newQuery()
            ->whereNull(EUserModel::KEY_IS_ACTIVE)
            ->get();

        $result = $results->first();

        $this->assertCount(1, $results);

        $this->assertTrue(is_null($result->{EUserModel::KEY_IS_ACTIVE}));


    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function test_where_not_null_result(): void
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $results = EUserModel::newQuery()
            ->whereNotNull(EUserModel::KEY_IS_ACTIVE)
            ->get();

        $result = $results->first();

        $this->assertCount(1, $results);

        $this->assertTrue(!is_null($result->{EUserModel::KEY_IS_ACTIVE}));
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function test_or_where_null_result()
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $results = EUserModel::newQuery()
            ->orWhereNull(EUserModel::KEY_IS_ACTIVE)
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function test_or_where_not_null_result(): void
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $results = EUserModel::newQuery()
            ->orWhereNotNull(EUserModel::KEY_IS_ACTIVE)
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function test_get_function_without_take()
    {
        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();

        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';

        $elasticsearchModel->mustBeSync()->save();

        $results = EUserModel::newQuery()
            ->orWhereNotNull(EUserModel::KEY_IS_ACTIVE)
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function test_chunk_method(): void
    {
        $elasticsearchModel = EUserModel::newQuery();
        $elasticsearchModel->{EUserModel::KEY_AGE} = 22;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 1;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_IS_ACTIVE} = true;
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';
        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();
        $elasticsearchModel->{EUserModel::KEY_AGE} = 45;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 2;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';
        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();
        $elasticsearchModel->{EUserModel::KEY_AGE} = 446;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 6;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';
        $elasticsearchModel->mustBeSync()->save();

        $elasticsearchModel = EUserModel::newQuery();
        $elasticsearchModel->{EUserModel::KEY_AGE} = 455;
        $elasticsearchModel->{BaseElasticsearchModel::KEY_ID} = 10;
        $elasticsearchModel->{EUserModel::KEY_NAME} = 'mohammad';
        $elasticsearchModel->{EUserModel::KEY_DESCRIPTION} = 'description';
        $elasticsearchModel->mustBeSync()->save();

        $iterator = 0;

        EUserModel::newQuery()
            ->chunk(2, function (Collection $collection) use (&$iterator) {
                $iterator++;

                $this->assertCount(2, $collection);

            });

        $this->assertEquals(2, $iterator);
    }


}