<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use Illuminate\Foundation\Testing\TestCase;
use ReflectionException;
use Tests\CreatesApplication;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Throwable;


class ElasticQueryBuilderIntegrationTest extends TestCase
{
    use CreatesApplication;
    use WithoutMiddleware;

    public elasticModel $elastic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->elastic = new elasticModel();

        $this->loadTestMigration();

        $this->migrateTestMigration();
    }


    private function loadTestMigration(): void
    {
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/../Mock');
    }

    private function migrateTestMigration(): void
    {
        $this->artisan(
            'migrate --path=' . database_path('migrations/2023_03_26_create_elastic_search_migrations_logs_table.php')
        );

        $this->artisan('elastic:migrate');
    }

    public function tearDown(): void
    {
        $this->rollbackTestMigration();

        parent::tearDown();
    }

    public function rollbackTestMigration(): void
    {
        $this->artisan('elastic:migrate --reset');
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function testFindMethod()
    {
        $data = [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text'
        ];

        $this->elastic->create($data);

        /**
         * elastic implements creating ,updating and deleting action as  asynchronous,
         * so we wait 2 seconds to be sure that elasticsearch added the data
         */
        sleep(2);

        $record = $this->elastic->find($data['id']);

        $this->assertEquals($data, $record->getAttributes());
    }


    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function testCanCreateData()
    {
        $data = [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text'
        ];

        $result = $this->elastic->create($data);


        sleep(2);

        $attributes = $result->attributes;

        $this->assertEquals($attributes, $data);

        $record = $this->elastic->find($data['id']);

        $this->assertEquals($data, $record->getAttributes());
    }


    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function testSetNullForUndefinedMappedData()
    {
        $data = [
            'id' => 1,
            'details' => 'this is test text'
        ];

        $this->elastic->create($data);

        sleep(2);

        $result = $this->elastic->find(1);

        $this->assertEquals(['name' => null, 'is_active' => null, 'id' => 1, 'details' => 'this is test text'],
            $result->attributes);
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     */
    public function testCanNotDefineUnMappedData()
    {
        $data = [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'not_defined_field' => 'value'
        ];

        $this->expectException(FieldNotDefinedInIndexException::class);

        $this->elastic->create($data);
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function testCanUpdateData()
    {
        $data = [
            'id' => 1,
            'details' => 'this is test text'
        ];

        $this->elastic->create($data);

        sleep(2);

        $model = $this->elastic->find(1);

        $newData = [
            'name' => 'mohammad',
            'is_active' => true,
        ];

        $model->update($newData);

        sleep(2);

        $model = $this->elastic->find(1);

        $expectation = [
            "id" => "1",
            "is_active" => true,
            "name" => "mohammad",
            "details" => "this is test text"
        ];

        $this->assertEquals($expectation, $model->getAttributes());
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function testCanNotUpdateUndefinedFields()
    {
        $data = [
            'id' => 1,
            'details' => 'this is test text'
        ];

        $this->elastic->create($data);

        sleep(2);

        $model = $this->elastic->find(1);

        $newData = [
            'not_defined' => 'mohammad',
            'is_active' => true,
        ];

        $this->expectException(FieldNotDefinedInIndexException::class);

        $model->update($newData);
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
            'id' => 1,
            'details' => 'this is test text'
        ];

        $this->elastic->create($data);

        sleep(2);

        $model = $this->elastic->find(1);

        $model->delete();

        sleep(2);

        $model = $this->elastic->find(1);

        $this->assertEquals(null, $model);
    }


    /**
     * @return void
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     */
    public function testSelect()
    {
        $data = [
            'id' => 1,
            'details' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'details' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'details' => 'number 3'
        ];

        $this->elastic->create($data);

        $this->elastic->create($data2);

        $this->elastic->create($data3);

        sleep(2);

        $results = $this->elastic->select('name', 'id')
            ->get();


        $firstResultAttributes = $results[0]->getAttributes();

        $this->assertEquals(['name', 'id'], array_keys($firstResultAttributes));
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     */
    public function testTake()
    {
        $data = [
            'id' => 1,
            'details' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'details' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'details' => 'number 3'
        ];

        $this->elastic->create($data);

        $this->elastic->create($data2);

        $this->elastic->create($data3);

        sleep(2);

        $results = $this->elastic->take(1)
            ->get();

        $this->assertEquals(1, $results->count());
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     */
    public function testOffset()
    {
        $data = [
            'id' => 1,
            'details' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'details' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'details' => 'number 3'
        ];

        $this->elastic->create($data);

        $this->elastic->create($data2);

        $this->elastic->create($data3);

        sleep(2);

        $results = $this->elastic->offset(1)
            ->take(1)
            ->get();


        $this->assertEquals(2, $results[0]->getAttributes()['id']);
    }

    /**
     * @return void
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function testWhereEqualCondition()
    {
        $data = [
            'id' => 1,
            'details' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'details' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'details' => 'number 3'
        ];

        $this->elastic->create($data);

        $this->elastic->create($data2);

        $this->elastic->create($data3);

        sleep(2);

        $results = $this->elastic->where('name', 'ali')
            ->select('name')
            ->get();

        $this->assertEquals(1, $results->count());

        $firstResult = $results[0]->attributes['name'];

        $this->assertEquals('ali', $firstResult);
    }

    /**
     * @throws RequestException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function testWhereNotEqualCondition()
    {
        $data = [
            'id' => 1,

            'name' => 'first',
            'details' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'name' => 'second',
            'details' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'details' => 'number 3'
        ];

        $this->elastic->create($data);

        $this->elastic->create($data2);

        $this->elastic->create($data3);

        sleep(2);

        $results = $this->elastic
            ->where('name', '<>', 'ali')
            ->select('name')
            ->get();


        $this->assertEquals(2, $results->count());

        $this->assertEquals('first', $results[0]->attributes['name']);

        $this->assertEquals('second', $results[1]->attributes['name']);
    }

    /**
     * @throws RequestException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function testOrWhereCondition()
    {
        $data = [
            'id' => 1,

            'name' => 'first',
            'details' => 'number one'
        ];

        $data2 = [
            'id' => 2,
            'name' => 'second',
            'details' => 'number 2'
        ];

        $data3 = [
            'id' => 3,
            'name' => 'ali',
            'details' => 'number 3'
        ];

        $this->elastic->create($data);

        $this->elastic->create($data2);

        $this->elastic->create($data3);

        sleep(2);

        $results = $this->elastic
            ->where('name', 'ali')
            ->orWhere('name', 'second')
            ->select('name')
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertEquals('ali', $results[1]->attributes['name']);

        $this->assertEquals('second', $results[0]->attributes['name']);
    }

    public function testOrWhereNotCondition()
    {
    }

    public function testWhereBetweenCondition()
    {
    }

    public function testNotBetweenCondition()
    {
    }

    public function testOrBetweenCondition()
    {
    }

    public function testOrWhereNotBetweenCondition()
    {
    }

    public function testOrderBy()
    {
    }

    public function testWhereTerm()
    {
    }

    public function testOrWhereTerm()
    {
    }

    public function testWhereNotTerm()
    {
    }

    public function testOrWhereNotTerm()
    {
    }

    public function testWhereEqual()
    {
    }

    public function testOrWhereEqual()
    {
    }


    public function testWhereNotEqual()
    {
    }

    public function testOrWhereNotEqual()
    {
    }

    public function testWhereGreaterThan()
    {
    }

    public function testOrWhereGreaterThan()
    {
    }

    public function testWhereLessThan()
    {
    }

    public function testOrWhereLessThan()
    {
    }

    public function testWhereLike()
    {
    }

    public function testWhereNotLike()
    {
    }

    public function testOrWhereLike()
    {
    }

    public function testOrWhereNotLike()
    {
    }

    public function testWhereGTE()
    {
    }

    public function testOrWhereGTE()
    {
    }

    public function testWhereLTE()
    {
    }

    public function testOrWhereLTE()
    {
    }


}