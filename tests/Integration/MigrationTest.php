<?php

namespace Tests\Integration;

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
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Migration\AlterElasticIndexMigrationInterface;

class MigrationTest extends TestCase
{
    use CreatesApplication;
    use WithoutMiddleware;
    
    public ElasticApiService $elasticApiService;

    public BaseElasticMigration $dummyMigration;

    public BaseElasticMigration $dummyMigrationAlterState;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function () {
            // remove all the current migrations and all the data exists in elasticsearch
            Artisan::call('migrate:fresh');
            ElasticSchema::deleteIndexIfExists(EUserModel::class);


            // setup test migrations as property
            $this->dummyMigration = require __DIR__ . '/../DummyRequirements/Migrations/2023_04_16_074007_create_tests_table.php';

            $this->dummyMigrationAlterState = new class extends BaseElasticMigration implements
                AlterElasticIndexMigrationInterface {

                public function getModel(): string
                {
                    return EUserModel::class;
                }

                public function schema(BaseElasticMigration $mapper)
                {
                    $mapper->string('city');
                }

                public function alterDown(BaseElasticMigration $mapper): void
                {
                    $mapper->dropField('city');
                }
            };

            // setup elastic api service
            $this->elasticApiService = new ElasticApiService();
        });

        parent::setUp();
    }


    /**
     * @group MigrationTest
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testUpMethodInMigrationsInCreatingState()
    {
        $this->dummyMigration->up();

        sleep(2);

        $test = new EUserModel();

        $expectedMappings = [
            EUserModel::KEY_AGE,
            EUserModel::KEY_DESCRIPTION,
            EUserModel::KEY_IS_ACTIVE,
            EUserModel::KEY_NAME
        ];

        $actualValues = array_keys($test->getMappings());

        $this->assertSame($expectedMappings, $actualValues);

        $this->dummyMigration->down();
    }


    /**
     * @group MigrationTest
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testDownMethodInMigrationInCreatingState()
    {
        $this->dummyMigration->up();

        $this->dummyMigration->down();

        $this->assertThrows(function () {
            $test = new EUserModel();
            $test->getMappings();
        }, ClientException::class);
    }

    /**
     * @group MigrationTest
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testAlterStateMigrationForAddField()
    {
        $this->dummyMigration->up();

        $this->dummyMigrationAlterState->up();

        sleep(3);

        $test = new EUserModel();

        $actualMappings = array_keys($test->getMappings());

        $expectedMappings = [
            EUserModel::KEY_NAME,
            EUserModel::KEY_AGE,
            EUserModel::KEY_IS_ACTIVE,
            EUserModel::KEY_DESCRIPTION,
            'city'
        ];

        // sorting expectations (base elastic mapping sort result)
        sort($expectedMappings);

        $this->assertSame($expectedMappings, $actualMappings);

        $this->dummyMigration->down();
    }

    /**
     * @group MigrationTest
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testMigrationDropField()
    {
        $this->dummyMigration->up();

        $this->dummyMigrationAlterState->up();

        sleep(3);

        $expectedMappings = [
            EUserModel::KEY_NAME,
            EUserModel::KEY_AGE,
            EUserModel::KEY_IS_ACTIVE,
            EUserModel::KEY_DESCRIPTION,
        ];

        sort($expectedMappings);

        $this->dummyMigrationAlterState->down();

        $test = new EUserModel();

        $actualMappings = array_keys($test->getMappings());

        $this->assertSame($expectedMappings, $actualMappings);

        $this->dummyMigration->down();
    }
}