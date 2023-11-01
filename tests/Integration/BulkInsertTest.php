<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use ReflectionException;
use Tests\DummyRequirements\Models\EUserModel;
use Tests\TestCase\Integration\BaseIntegrationTestCase;

class BulkInsertTest extends BaseIntegrationTestCase
{

    /**
     * @return void
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws JsonException
     * @throws IndexNamePatternIsNotValidException
     * @throws \Throwable
     */
    public function test_can_insert_multi_user_with_one_method_call():void
    {
        $items = [
            [
                'id' => 10,
                'name' => 'Mohammad Amiri',
                'is_active' => true,
                'age' => 30,
                'description' => 'null'
            ],
            [
                'id' => 20,
                'name' => 'Ali Ghorbani',
                'is_active' => true,
                'age' => 20,
                'description' => 'null'
            ]
        ];

        EUserModel::newQuery()
            ->mustBeSync()
            ->saveMany($items,true);

        $result = EUserModel::newQuery()
            ->whereIn('id', [10, 20])
            ->get();

        $this->assertEquals(count($items), $result->count());
    }
}