<?php

namespace Tests\Unit;

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use PHPUnit\Framework\TestCase;

class MigrationsTest extends TestCase
{


    public function testNested()
    {
        $baseMigrationMock = $this->getMockBuilder(BaseElasticMigration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $baseMigrationMock
            ->method('isCreationState')
            ->willReturn(true);

        /**
         * @type BaseElasticMigration $dummy
         */
        $dummy = require __DIR__ . '/../Dummy/2023_04_16_074007_create_tests_table.php';

        $dummy->object('category', [
            'is_active' => BaseElasticMigration::TYPE_BOOLEAN,
            'name' => BaseElasticMigration::TYPE_STRING,

        ]);

        $expected = [
            'mappings' => [
                'properties' => [
                    'category' => [
                        'type' => 'nested',
                        'properties' => [
                            'is_active' => [
                                'type' => BaseElasticMigration::TYPE_BOOLEAN
                            ],
                            'name' => [
                                'type' => BaseElasticMigration::TYPE_STRING
                            ]
                        ]
                    ],

                ]
            ]
        ];

        $this->assertSame($expected, $dummy->schema);
    }
}