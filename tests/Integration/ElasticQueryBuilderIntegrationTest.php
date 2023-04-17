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
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    public function testCanCreateData()
    {
    }

    public function testSetNullForUndefinedMappedData()
    {
    }

    public function testCanNotDefineUnMappedData()
    {
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


}