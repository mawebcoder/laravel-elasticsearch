<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as ElasticSearchModel;


abstract class ElasticSearchIntegrationTestCase extends TestCase
{
    use CreatesApplication;

    public ElasticSearchModel $elastic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->elastic = new ElasticSearchModel();

        $this->loadTestMigration();

        $this->migrateTestMigration();
    }

    private function loadTestMigration(): void
    {
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/Dummy');
    }

    private function migrateTestMigration(): void
    {
        $this->artisan(
            'migrate --path=' . database_path('migrations/2023_03_26_create_elastic_search_migrations_logs_table.php')
        );

        sleep(2);

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

    public function insertElasticDocument(BaseElasticsearchModel $model, array $data): BaseElasticsearchModel
    {
        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        $result = $model->save();

        sleep(1);

        return $result;
    }
}