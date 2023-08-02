<?php

namespace Tests\Integration;

use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Throwable;
use ReflectionException;
use GuzzleHttp\Exception\GuzzleException;
use Tests\ElasticSearchIntegrationTestCase;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

class ElasticQueryBuilderIntegrationTest extends ElasticSearchIntegrationTestCase
{


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testFindMethod()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 2,
            elasticModel::KEY_NAME => $this->faker->name,
            elasticModel::KEY_AGE => 23,
            elasticModel::KEY_IS_ACTIVE => true,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $elasticModel = new elasticModel();

        $this->insertElasticDocument($elasticModel, $data);

        $record = elasticModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals($data, $record->getAttributes());
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testSetNullForUndefinedMappedData()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 1,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $elasticModel = new elasticModel();

        $this->insertElasticDocument($elasticModel, $data);

        $result = elasticModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals(
            [
                BaseElasticsearchModel::KEY_ID => 1,
                elasticModel::KEY_DESCRIPTION => $data[elasticModel::KEY_DESCRIPTION]
            ],
            $result->attributes
        );
    }


    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testCanUpdateData()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 1,
            elasticModel::KEY_IS_ACTIVE => false,
            elasticModel::KEY_NAME => $this->faker->name,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $this->insertElasticDocument(new elasticModel(), $data);

        $model = elasticModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $newData = [
            elasticModel::KEY_NAME => $this->faker->unique()->name,
            elasticModel::KEY_IS_ACTIVE => true,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $this->update($model, $newData);

        $model = elasticModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $expectation = [
            BaseElasticsearchModel::KEY_ID => $data[BaseElasticsearchModel::KEY_ID],
            ...$newData
        ];

        $this->assertEquals($expectation, $model->getAttributes());
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function testCanDeleteDataByModelRecord()
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => 1,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $this->insertElasticDocument(new elasticModel(), $data);

        $model = elasticModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $model->mustBeSync()->delete();

        $model = elasticModel::newQuery()->find($data[BaseElasticsearchModel::KEY_ID]);

        $this->assertEquals(null, $model);
    }


    /**
     * @return void
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testSelect()
    {
        $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->select(elasticModel::KEY_NAME, BaseElasticsearchModel::KEY_ID)
            ->get();

        $firstResultAttributes = $results->first()->getAttributes();

        $this->assertEquals([elasticModel::KEY_NAME, BaseElasticsearchModel::KEY_ID],
            array_keys($firstResultAttributes));
    }


    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testTake()
    {
        $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->take(1)
            ->get();

        $this->assertEquals(1, $results->count());
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testOffset()
    {
        $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->offset(1)
            ->get();

        $this->assertCount(2, $results);
    }


    /**
     * @return void
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereEqualCondition()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_NAME, $data[0]->{elasticModel::KEY_NAME})
            ->select(elasticModel::KEY_NAME)
            ->get();

        $this->assertEquals(1, $results->count());

        $firstResult = $results->first()->attributes[elasticModel::KEY_NAME];

        $this->assertEquals($data[0]->{elasticModel::KEY_NAME}, $firstResult);
    }


    /**
     * @throws RequestException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws GuzzleException
     */
    public function testWhereNotEqualCondition()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_NAME, '<>', $data[0]->{elasticModel::KEY_NAME})
            ->select(elasticModel::KEY_NAME)
            ->get();

        $this->assertEquals(2, $results->count());
    }


    /**
     * @throws RequestException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testOrWhereCondition()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_NAME, $data[0]->{elasticModel::KEY_NAME})
            ->orWhere(elasticModel::KEY_AGE, $data[1]->{elasticModel::KEY_AGE})
            ->select(elasticModel::KEY_NAME, elasticModel::KEY_AGE)
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_NAME} == $data[0]->{elasticModel::KEY_NAME})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} == $data[1]->{elasticModel::KEY_AGE})
        );
    }


    /**
     * @throws RequestException
     * @throws WrongArgumentType
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testNotBetweenCondition()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->whereNotBetween(
                elasticModel::KEY_AGE,
                [$data[0]->{elasticModel::KEY_AGE}, $data[1]->{elasticModel::KEY_AGE}]
            )->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === $data[2]->{elasticModel::KEY_AGE}));
    }


    /**
     * @throws RequestException
     * @throws WrongArgumentType
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws GuzzleException
     */
    public function testOrBetweenCondition()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, $data[1]->{elasticModel::KEY_AGE})
            ->orWhereBetween(
                elasticModel::KEY_AGE,
                [$data[0]->{elasticModel::KEY_AGE}, $data[2]->{elasticModel::KEY_AGE}]
            )->get();

        $this->assertEquals(3, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => intval($row->{elasticModel::KEY_AGE}) === $data[0]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => intval($row->{elasticModel::KEY_AGE}) === $data[1]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => intval($row->{elasticModel::KEY_AGE}) === $data[2]->{elasticModel::KEY_AGE})
        );
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws InvalidSortDirection
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testOrderByAsc()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->orderBy(elasticModel::KEY_AGE)
            ->get();

        $first = $results->first();

        $second = $results[1];

        $third = $results[2];

        $this->assertEquals($data[0]->{elasticModel::KEY_AGE}, $first->{elasticModel::KEY_AGE});

        $this->assertEquals($data[1]->{elasticModel::KEY_AGE}, $second->{elasticModel::KEY_AGE});

        $this->assertEquals($data[2]->{elasticModel::KEY_AGE}, $third->{elasticModel::KEY_AGE});
    }


    /**
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws InvalidSortDirection
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testOrderByDesc()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->orderBy(elasticModel::KEY_AGE, 'desc')
            ->get();

        $first = $results->first();

        $second = $results[1];

        $third = $results[2];

        $this->assertEquals($data[2]->{elasticModel::KEY_AGE}, $first->{elasticModel::KEY_AGE});

        $this->assertEquals($data[1]->{elasticModel::KEY_AGE}, $second->{elasticModel::KEY_AGE});

        $this->assertEquals($data[0]->{elasticModel::KEY_AGE}, $third->{elasticModel::KEY_AGE});
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereTerm()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->whereTerm(elasticModel::KEY_NAME, $data[0]->{elasticModel::KEY_NAME})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_NAME} === $data[0]->{elasticModel::KEY_NAME})
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testOrWhereTerm()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_NAME, $data[0]->{elasticModel::KEY_NAME})
            ->orWhereTerm(elasticModel::KEY_NAME, $data[1]->{elasticModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_NAME} === $data[0]->{elasticModel::KEY_NAME})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_NAME} === $data[1]->{elasticModel::KEY_NAME})
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereNotTerm()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->whereTerm(elasticModel::KEY_NAME, '<>', $data[0]->{elasticModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->{elasticModel::KEY_NAME}));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[2]->{elasticModel::KEY_NAME}));
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function testOrWhereEqual()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_NAME, $data[0]->{elasticModel::KEY_NAME})
            ->orWhere(elasticModel::KEY_NAME, $data[1]->{elasticModel::KEY_NAME})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[0]->{elasticModel::KEY_NAME}));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->{elasticModel::KEY_NAME}));
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereGreaterThan()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, '>', $data[0]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertCount(2, $results);

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[1]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[2]->{elasticModel::KEY_AGE})
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function testOrWhereGreaterThan()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, $data[0]->{elasticModel::KEY_AGE})
            ->orWhere(elasticModel::KEY_AGE, '>', $data[1]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[2]->{elasticModel::KEY_AGE})
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereLessThan()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, '<', $data[1]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->age === $data[0]->{elasticModel::KEY_AGE}));
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function testOrWhereLessThan()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, $data[2]->{elasticModel::KEY_AGE})
            ->orWhere(elasticModel::KEY_AGE, '<', $data[1]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[2]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[0]->{elasticModel::KEY_AGE})
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function testWhereLike()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_DESCRIPTION, 'like', $data[0]->{elasticModel::KEY_DESCRIPTION})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{elasticModel::KEY_DESCRIPTION} === $data[0]->{elasticModel::KEY_DESCRIPTION}
            )
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function testWhereNotLike()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_DESCRIPTION, 'not like', $data[0]->{elasticModel::KEY_DESCRIPTION})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{elasticModel::KEY_DESCRIPTION} === $data[1]->{elasticModel::KEY_DESCRIPTION}
            )
        );
        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{elasticModel::KEY_DESCRIPTION} === $data[2]->{elasticModel::KEY_DESCRIPTION}
            )
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testOrWhereLike()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, $data[0]->{elasticModel::KEY_AGE})
            ->orWhere(elasticModel::KEY_DESCRIPTION, 'like', $data[1]->{elasticModel::KEY_DESCRIPTION})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[0]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(
                fn($row) => $row->{elasticModel::KEY_DESCRIPTION} === $data[1]->{elasticModel::KEY_DESCRIPTION}
            )
        );
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function testWhereGreaterThanOrEqual()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, '>=', $data[1]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[2]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[1]->{elasticModel::KEY_AGE})
        );
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function testOrWhereGreaterThanOrEqual()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, $data[0]->{elasticModel::KEY_AGE})
            ->orWhere(elasticModel::KEY_AGE, '>=', $data[1]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(3, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[0]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[1]->{elasticModel::KEY_AGE})
        );
        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[2]->{elasticModel::KEY_AGE})
        );
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereLessThanOrEqual()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, '<=', $data[0]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[0]->{elasticModel::KEY_AGE})
        );
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function testOrWhereLessThanOrEqual()
    {
        $data = $this->registerSomeRecords();

        $results = elasticModel::newQuery()
            ->where(elasticModel::KEY_AGE, $data[2]->{elasticModel::KEY_AGE})
            ->orWhere(elasticModel::KEY_AGE, '<=', $data[0]->{elasticModel::KEY_AGE})
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[0]->{elasticModel::KEY_AGE})
        );

        $this->assertTrue(
            $results->contains(fn($row) => $row->{elasticModel::KEY_AGE} === $data[2]->{elasticModel::KEY_AGE})
        );
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testGetMappings()
    {
        $mappings = elasticModel::newQuery()->getMappings();

        $expected = [
            elasticModel::KEY_AGE => [
                'type' => 'integer'
            ],

            elasticModel::KEY_IS_ACTIVE => [
                "type" => "boolean"
            ],
            elasticModel::KEY_NAME => [
                "type" => "keyword"
            ],
            elasticModel::KEY_DESCRIPTION => [
                "type" => "text"
            ]
        ];

        $this->assertEquals($expected, $mappings);
    }
}