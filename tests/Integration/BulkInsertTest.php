<?php

namespace Tests\Integration;

use Tests\ElasticSearchIntegrationTestCase;
use Tests\DummyRequirements\Models\EUserModel;

class BulkInsertTest extends ElasticSearchIntegrationTestCase
{
    /**
     * NOTHING
     */
    public function test_can_insert_multi_user_with_one_method_call()
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

        $result = EUserModel::newQuery()
            ->mustBeSync()
            ->saveMany($items);

        $this->assertTrue($result);

        $this->assertEquals(count($items), EUserModel::newQuery()->count());
    }
}