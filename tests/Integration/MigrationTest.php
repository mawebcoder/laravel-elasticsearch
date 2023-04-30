<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\Test;
use ReflectionException;
use Tests\CreatesApplication;

class MigrationTest extends TestCase
{
    use CreatesApplication;
    use WithoutMiddleware;

    public BaseElasticMigration $dummyMigration;

    public ElasticApiService $elasticApiService;

    protected function setUp(): void
    {
        $this->dummyMigration = require __DIR__ . '/../Dummy/2023_04_16_074007_create_tests_table.php';

        $this->elasticApiService = new ElasticApiService();
    }


//    protected function tearDown(): void
//    {
//       $this->dummyMigration->down();
//    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function testUpMethodInMigrationsInCreatingState()
    {
        $this->dummyMigration->up($this->elasticApiService);

//        $test = new Test();
//
//        sleep(2);
//
//        $mappings = $test->getMappings();
//
//        dump($mappings);
    }
}