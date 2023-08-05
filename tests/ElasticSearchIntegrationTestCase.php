<?php

namespace Tests;


use Throwable;
use ReflectionClass;
use ReflectionException;
use Illuminate\Support\Facades\Artisan;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\RequestException;
use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;

abstract class ElasticSearchIntegrationTestCase extends TestCase
{
    use WithFaker;
    use CreatesApplication;

    const MODELS_MUST_TRUNCATE_BETWEEN_TEST_CASE = [
        EUserModel::class
    ];

    public static function tearDownAfterClass(): void
    {
        Artisan::call('elastic:migrate --just --fresh');
    }

    protected function tearDown(): void
    {
        self::truncateTestModels();

        parent::tearDown();
    }

    public static function setUpBeforeClass(): void
    {
        self::bootApplication();

        self::bootTestMigrations();
    }

    public static function bootApplication(): void
    {
        $classReferenceCurrentTest = (new ReflectionClass(static::class))->getName();

        $integrationTest = new static($classReferenceCurrentTest);

        $integrationTest->setUp();
    }

    /**
     * @return void
     */
    public static function bootTestMigrations(): void
    {
        // remove all the migration from primary database
        Artisan::call(
            'migrate --path=' . database_path('migrations/2023_03_26_create_elastic_search_migrations_logs_table.php')
        );

        // load the test elastic migrations
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/DummyRequirements/Migrations');
        Artisan::call('elastic:migrate --fresh');
    }

    public static function truncateTestModels()
    {
        /* @var BaseElasticsearchModel $model */
        foreach (self::MODELS_MUST_TRUNCATE_BETWEEN_TEST_CASE as $model) {
                $model::newQuery()->truncate();
        }
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
    public function registerSomeTestUserRecords(): array
    {
        $userId1 = $this->faker->unique()->numberBetween(1, 9);
        $userId2 = $this->faker->numberBetween(10, 14);
        $userId3 = $this->faker->numberBetween(15, 20);

        EUserModel::newQuery()->mustBeSync()->saveMany([
            [
                BaseElasticsearchModel::KEY_ID => $userId1,
                EUserModel::KEY_NAME => $this->faker->unique()->name,
                EUserModel::KEY_AGE => 22,
                EUserModel::KEY_DESCRIPTION => $this->faker->word
            ],
            [
                BaseElasticsearchModel::KEY_ID => $userId2,
                EUserModel::KEY_NAME => $this->faker->unique()->name,
                EUserModel::KEY_AGE => 26,
                EUserModel::KEY_DESCRIPTION => $this->faker->word
            ],
            [
                BaseElasticsearchModel::KEY_ID => $userId3,
                EUserModel::KEY_NAME => $this->faker->unique()->name,
                EUserModel::KEY_AGE => 30,
                EUserModel::KEY_DESCRIPTION => $this->faker->word
            ]
        ]);

        return [
            EUserModel::newQuery()->find($userId1),
            EUserModel::newQuery()->find($userId2),
            EUserModel::newQuery()->find($userId3)
        ];
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function truncateModel(BaseElasticsearchModel $elasticsearch): void
    {
        $elasticsearch->mustBeSync()->truncate();
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function update(BaseElasticsearchModel $elasticsearch, array $data): bool
    {
        return $elasticsearch->mustBeSync()->update($data);
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