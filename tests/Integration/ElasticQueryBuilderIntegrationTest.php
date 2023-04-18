<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use Illuminate\Foundation\Testing\TestCase;
use ReflectionException;
use Tests\CreatesApplication;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;


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
         * elastic implements creating ,updating and deleting action as  asynchronous
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

    public function testCanUpdateData()
    {
    }

    public function testCanDeleteData()
    {
    }


    public function testSelect()
    {
    }

    public function testTake()
    {
    }

    public function testOffset()
    {
    }

    public function testWhereCondition()
    {
    }

    public function testWhereNotCondition()
    {
    }

    public function testOrWhereCondition()
    {
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