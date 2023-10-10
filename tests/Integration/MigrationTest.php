<?php

namespace Tests\Integration;

use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
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

        $this->dummyMigration = require __DIR__ . '/../DummyRequirements/Migrations/2023_04_16_074007_create_tests_table.php';

    }

    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function testUpMethodInMigrationsInCreatingState(): void
    {
        $this->dummyMigration->up();

        sleep(1);

        $this->assertTrue(Elasticsearch::hasIndex($this->dummyMigration->getModelIndex()));

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