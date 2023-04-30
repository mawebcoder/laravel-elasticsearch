<?php

namespace Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\Test;
use ReflectionException;
use Tests\CreatesApplication;
use Throwable;

class MigrationTest extends TestCase
{
    use CreatesApplication;
    use WithoutMiddleware;

    public BaseElasticMigration $dummyMigration;

    public readonly string $dummyMigrationAlterStatePath;
    public ElasticApiService $elasticApiService;

    public Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyMigrationAlterStatePath = __DIR__ . '/../Dummy/2023_04_30_074007_alter_tests_index.php';

        $this->client = new Client();

        $this->dummyMigration = require __DIR__ . '/../Dummy/2023_04_16_074007_create_tests_table.php';

        $this->elasticApiService = new ElasticApiService();
    }


    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testUpMethodInMigrationsInCreatingState()
    {
        $this->dummyMigration->up();

        $test = new Test();

        sleep(2);

        $expectedMappings = [
            'age',
            'details',
            'id',
            'is_active',
            'name'
        ];

        $actualValues = array_keys($test->getMappings());

        $this->assertSame($expectedMappings, $actualValues);

        $this->dummyMigration->down();
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testDownMethodInMigrationInCreatingState()
    {
        $this->dummyMigration->up();

        $this->dummyMigration->down();

        $this->withExceptionHandling();

        try {
            $test = new Test();

            $test->getMappings();
        } catch (Throwable $exception) {
            $this->assertStringContainsString(
                '"type":"index_not_found_exception","reason":"no such index [test]'
                ,
                $exception->getMessage()
            );
        }
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testAlterStateMigrationForDropField()
    {
        $this->dummyMigration->up();

        sleep(3);

        /**
         * @type BaseElasticMigration $alterDummy
         */
        $alterDummy = require $this->dummyMigrationAlterStatePath;

        $alterDummy->up();

        sleep(2);

        $test = new Test();

        $actualMappings = array_keys($test->getMappings());

        $expectedMappings = [
            'body',
            'details',
            'id',
            'is_active',
            'name'
        ];

        $this->assertSame($expectedMappings, $actualMappings);

        $this->dummyMigration->down();
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testDownInAlterStateMigration()
    {
        $this->dummyMigration->up();

        sleep(3);

        /**
         * @type BaseElasticMigration $alterDummyUp
         */
        $alterDummyUp = require $this->dummyMigrationAlterStatePath;

        $alterDummyUp->up();

        sleep(2);

        /**
         * @type BaseElasticMigration $alterDummyDown
         */
        $alterDummyDown = require $this->dummyMigrationAlterStatePath;

        $alterDummyDown->down();

        sleep(2);

        $test = new Test();

        $actualMappings = array_keys($test->getMappings());

        $expected = [
            'age',
            'body',
            'details',
            'id',
            'is_active',
            'name'
        ];

        $this->assertSame($expected, $actualMappings);

        $this->dummyMigration->down();
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testAlterStateForAddingField()
    {
        $this->dummyMigration->up();

        sleep(3);

        /**
         * @type BaseElasticMigration $alterDummyUp
         */
        $alterDummyUp = require $this->dummyMigrationAlterStatePath;

        $alterDummyUp->up();

        sleep(2);

        $test = new Test();

        $actualMappings = array_keys($test->getMappings());

        $expected = [
            'body',
            'details',
            'id',
            'is_active',
            'name'
        ];

        $this->assertSame($expected, $actualMappings);

        $this->dummyMigration->down();
    }

}