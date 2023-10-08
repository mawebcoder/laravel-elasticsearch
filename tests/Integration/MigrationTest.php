<?php

namespace Tests\Integration;

use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
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

class MigrationTest extends BaseIntegrationTestCase
{
    use CreatesApplication;
    use HasFakeMigration;
    use WithoutMiddleware;

    public ElasticApiService $elasticApiService;

    public BaseElasticMigration $dummyMigration;

    public BaseElasticMigration $dummyMigrationAlterState;


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

    public function testPrefixIndex(): void
    {
        $elasticsearch = new EUserModel();

        $index = $elasticsearch->getIndexWithPrefix();

        $this->assertEquals(config('elasticsearch.index_prefix') . $elasticsearch->getIndex(), $index);
    }

    public function test_has_index_method(): void
    {
        sleep(1);

        $result = Elasticsearch::hasIndex(EUserModel::INDEX_NAME);

        $this->assertTrue($result);
    }

    /**
     * @throws InvalidSortDirection
     */
    public function test_id_on_order_by(): void
    {
        $elasticModel = new EUserModel();

        $elasticModel->orderBy('id', 'desc');

        $this->assertEquals(
            [
                [
                    '_id' => [
                        'order' => 'desc'
                    ]
                ]
            ]
            , $elasticModel->search['sort']
        );
    }

}