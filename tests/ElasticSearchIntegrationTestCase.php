<?php

namespace Tests;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as ElasticSearchModel;
use ReflectionException;
use Throwable;

use ReflectionClass;

abstract class ElasticSearchIntegrationTestCase extends TestCase
{
    use CreatesApplication;

    use WithFaker;

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public static function setUpBeforeClass(): void
    {
        static::bootApplication();

        static::loadTestMigration();

        static::migrateTestMigration();

        static::migrateElasticsearchMigrations();

        elasticModel::newQuery()->mustBeSync()->truncate();
    }

    private static function bootApplication(): void
    {
        $testName = (new ReflectionClass(static::class))->getName();

        (new static($testName))->setUp();
    }

    private static function loadTestMigration(): void
    {
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/Dummy');
    }

    private static function migrateTestMigration(): void
    {
        Artisan::call(
            'migrate --path=' . database_path('migrations/2023_03_26_create_elastic_search_migrations_logs_table.php')
        );
    }

    public static function migrateElasticsearchMigrations(): void
    {
        Artisan::call('elastic:migrate --fresh');
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    protected function tearDown(): void
    {
        elasticModel::newQuery()
            ->mustBeSync()->truncate();

        parent::tearDown();
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function insertElasticDocument(BaseElasticsearchModel $model, array $data): BaseElasticsearchModel
    {
        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        return $model->mustBeSync()->save();
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
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

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function truncateModel(ElasticSearchModel $elasticsearch): void
    {
        $elasticsearch->mustBeSync()->truncate();
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function update(BaseElasticsearchModel $elasticsearch, array $data): void
    {
        $elasticsearch->mustBeSync()->update($data);
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function deleteModel(BaseElasticsearchModel $elasticsearch): void
    {
        $elasticsearch->mustBeSync()->delete();
    }


}