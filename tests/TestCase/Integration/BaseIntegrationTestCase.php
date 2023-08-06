<?php

namespace Tests\TestCase\Integration;


use ReflectionClass;
use Tests\CreatesApplication;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Tests\TestCase\Integration\Traits\HasDebugMode;
use Tests\TestCase\Integration\Traits\HasSyncOperation;
use Tests\TestCase\Integration\Traits\HasFakeMigration;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

class BaseIntegrationTestCase extends TestCase
{
    use WithFaker;
    use CreatesApplication;
    use HasDebugMode, HasSyncOperation, HasFakeMigration;

    const MODELS_MUST_TRUNCATE_BETWEEN_TEST_CASE = [
        EUserModel::class
    ];

    public static function setUpBeforeClass(): void
    {
        self::bootApplication();
        self::bootTestMigrations();
    }

    protected function setUp(): void
    {
        $this->printVerboseSetupDebugDetails();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->printVerboseTearDownDebugDetails();

        $this->tearDownMethodLogic();

        parent::tearDown();
    }

    /*
     * When in debug mode test failed you need to know!
     */
    public function onNotSuccessfulTest($e): never
    {
        $this->printVerboseOnNotSuccessfulTestDebugDetails();

        parent::onNotSuccessfulTest($e);
    }

    /**
     * Anything that you write here apply on each test (support both condition passed or failed!)
     *
     */
    private function tearDownMethodLogic(): void
    {
        self::truncateTestModels();
    }

    public static function bootApplication(): void
    {
        self::$isBootApplication = true;

        $classReferenceCurrentTest = (new ReflectionClass(static::class))->getName();
        $integrationTest = new self($classReferenceCurrentTest);
        $integrationTest->setUp();
    }

    /**
     * @return void
     */
    public static function bootTestMigrations(): void
    {
        self::printVerboseBootTestMigrationsDebugDetails();

        // sure, elastics-each logs migration migrate!
        if (!Schema::hasTable('elastic_search_migrations_logs')) {
            Artisan::call(
                'migrate --path=' . database_path(
                    'migrations/2023_03_26_create_elastic_search_migrations_logs_table.php'
                )
            );
        }

        // load the test elastic migrations
        Elasticsearch::loadMigrationsFrom(static::getMigrationPath());
        Artisan::call('elastic:migrate --fresh');
    }

    public static function truncateTestModels()
    {
        /* @var BaseElasticsearchModel $model */
        foreach (self::MODELS_MUST_TRUNCATE_BETWEEN_TEST_CASE as $model) {
            $model::newQuery()->mustBeSync()->truncate();
        }
    }

}