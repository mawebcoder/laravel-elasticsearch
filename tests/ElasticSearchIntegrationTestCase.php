<?php

namespace Tests;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as ElasticSearchModel;
use ReflectionException;


abstract class ElasticSearchIntegrationTestCase extends TestCase
{
    use CreatesApplication;
    use WithFaker;

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

       $this->freshMigrations();

        sleep(1);
    }



    public function freshMigrations(): void
    {
        $this->artisan('elastic:migrate --fresh');
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function insertElasticDocument(BaseElasticsearchModel $model, array $data): BaseElasticsearchModel
    {
        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        $result = $model->save();

        sleep(1);

        return $result;
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function registerSomeRecords(): array
    {
        $data = [
            BaseElasticsearchModel::KEY_ID => $this->faker->unique()->numberBetween(1, 20),
            elasticModel::KEY_NAME => $this->faker->unique()->name,
            elasticModel::KEY_AGE => 22,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $data2 = [
            BaseElasticsearchModel::KEY_ID => $this->faker->numberBetween(10, 20),
            elasticModel::KEY_NAME => $this->faker->unique()->name,
            elasticModel::KEY_AGE => 26,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $data3 = [
            BaseElasticsearchModel::KEY_ID => $this->faker->numberBetween(10, 20),
            elasticModel::KEY_NAME => $this->faker->unique()->name,
            elasticModel::KEY_AGE => 30,
            elasticModel::KEY_DESCRIPTION => $this->faker->word
        ];

        $elasticModelOne = new elasticModel();

        $this->insertElasticDocument($elasticModelOne, $data);

        $elasticModelTwo = new elasticModel();

        $this->insertElasticDocument($elasticModelTwo, $data2);

        $elasticModelThree = new elasticModel();

        $this->insertElasticDocument($elasticModelThree, $data3);

        return [
            $elasticModelOne,
            $elasticModelTwo,
            $elasticModelThree
        ];
    }
}