<?php

namespace Tests\Integration;

use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use Mawebcoder\Elasticsearch\Exceptions\IndicesAlreadyExistsException;
use Mawebcoder\Elasticsearch\Exceptions\IndicesNotFoundException;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use ReflectionClass;
use ReflectionException;
use Tests\CreatesApplication;
use Illuminate\Support\Facades\Artisan;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use Mawebcoder\Elasticsearch\ElasticSchema;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\RequestException;
use Tests\DummyRequirements\Models\EUserModel;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Tests\TestCase\Integration\BaseIntegrationTestCase;
use Tests\TestCase\Integration\Traits\HasFakeMigration;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Migration\AlterElasticIndexMigrationInterface;

class MigrationTest extends TestCase
{
    use CreatesApplication;
    use HasFakeMigration;
    use WithoutMiddleware;

    public ElasticApiService $elasticApiService;

    public BaseElasticMigration $dummyMigration;

    public BaseElasticMigration $dummyMigrationAlterState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropAllIndices();

        $this->dummyMigration = require __DIR__ . '/../DummyRequirements/Migrations/2023_04_16_074007_create_tests_table.php';

        $this->dummyMigrationAlterState = require __DIR__ . '/../DummyRequirements/AlterMigration/2023_09_11_090100_alter_tests_table.php';

    }

    protected function tearDown(): void
    {
        $this->dropAllIndices();

        parent::tearDown();
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException
     * @throws IndicesAlreadyExistsException
     * @throws IndicesNotFoundException
     */
    public function testUpMethodInMigrationsInCreatingState(): void
    {
        $this->dummyMigration->up();

        $this->assertTrue(Elasticsearch::hasIndex($this->dummyMigration->getModelIndex()));

        $this->dummyMigration->down();
    }


    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws IndicesAlreadyExistsException
     * @throws IndicesNotFoundException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function testDownMethodInMigrationInCreatingState(): void
    {
        $this->dummyMigration->up();

        $this->dummyMigration->down();

        $this->assertThrows(function () {
            $test = new EUserModel();
            $test->getMappings();
        }, ClientException::class);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws IndicesAlreadyExistsException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException|IndicesNotFoundException
     */
    public function testAlterStateMigrationForAddField(): void
    {
        $this->dummyMigration->up();

        $this->dummyMigrationAlterState->up();

        $test = new EUserModel();

        $actualMappings = array_keys($test->getMappings());

        $expectedMappings = [
            EUserModel::KEY_AGE,
            EUserModel::KEY_NAME,
            EUserModel::KEY_IS_ACTIVE,
            EUserModel::KEY_INFORMATION,
            EUserModel::KEY_DESCRIPTION,
            'city'
        ];

        // sorting expectations (base elastic mapping sort result)
        sort($expectedMappings);

        $this->assertEqualsCanonicalizing($expectedMappings, $actualMappings);
    }

    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws IndicesAlreadyExistsException
     * @throws IndicesNotFoundException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function testMigrationDropField(): void
    {
        $this->dummyMigration->up();

        $this->dummyMigrationAlterState->up();

        $expectedMappings = [
            EUserModel::KEY_AGE,
            EUserModel::KEY_NAME,
            EUserModel::KEY_IS_ACTIVE,
            EUserModel::KEY_INFORMATION,
            EUserModel::KEY_DESCRIPTION,
        ];

        sort($expectedMappings);

        $this->dummyMigrationAlterState->down();

        $test = new EUserModel();

        $actualMappings = array_keys($test->getMappings());

        $this->assertEqualsCanonicalizing($expectedMappings, $actualMappings);
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws IndicesAlreadyExistsException
     * @throws JsonException
     */
    public function test_has_index_method(): void
    {

        $this->dummyMigration->up();

        $result = Elasticsearch::hasIndex(EUserModel::INDEX_NAME);

        $this->assertTrue($result);
    }

    private function dropAllIndices(): void
    {
        $indices = Elasticsearch::getAllIndexes();

        foreach ($indices as $index) {
            Elasticsearch::dropIndexByName($index);
        }
    }

    public function test_throw_exception_while_creating_indices_if_indices_already_exists(): void
    {
        $this->assertThrows(function () {
            $this->dummyMigration->up();
            $this->dummyMigration->up();
        }, IndicesAlreadyExistsException::class);

    }

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws IndicesAlreadyExistsException
     * @throws GuzzleException
     */
    public function test_dynamic_mapping_is_shutdown_by_default(): void
    {
        $this->dummyMigration->up();

        $this->assertTrue(isset($this->dummyMigration->schema['mappings']['dynamic']));

        $this->assertFalse($this->dummyMigration->schema['mappings']['dynamic']);
    }

    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws IndicesAlreadyExistsException
     * @throws IndicesNotFoundException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function test_can_turn_on_dynamic_mapping(): void
    {
        $reflection = new ReflectionClass($this->dummyMigration);

        $object = $reflection->newInstance();

        $reflection->getProperty('isDynamicMapping')
            ->setValue($object, true);

        $object->up();

        $this->assertFalse(isset($object->schema['mappings']['dynamic']));
    }

}