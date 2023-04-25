<?php

namespace Tests\Unit;

use Mawebcoder\Elasticsearch\Exceptions\NotValidFieldTypeException;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use PHPUnit\Framework\TestCase;

class CreateMigrationsTest extends TestCase
{

    public BaseElasticMigration $dummy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummy = require __DIR__ . '/../Dummy/2023_04_16_074007_create_tests_table.php';

        $baseMigrationMock = $this->getMockBuilder(BaseElasticMigration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $baseMigrationMock
            ->method('isCreationState')
            ->willReturn(true);
    }

    public function testNested()
    {
        $this->dummy->object('category', [
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

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testEncounterErrorInWrongType()
    {
        $this->expectException(NotValidFieldTypeException::class);

        $this->dummy->object('category', [
            'is_active' => 'wrong',
        ]);
    }


    public function testIntegerFieldType()
    {
        $this->dummy->integer('age');

        $expected = [
            'mappings' => [
                'properties' => [
                    'age' => [
                        'type' => BaseElasticMigration::TYPE_INTEGER
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testBooleanType()
    {
        $this->dummy->boolean('is_active');

        $expected = [
            'mappings' => [
                'properties' => [
                    'is_active' => [
                        'type' => BaseElasticMigration::TYPE_BOOLEAN
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testSmallIntegerType()
    {
        $this->dummy->smallInteger('age');

        $expected = [
            'mappings' => [
                'properties' => [
                    'age' => [
                        'type' => 'short'
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testBigIntType()
    {
        $this->dummy->bigInteger('age');

        $expected = [
            'mappings' => [
                'properties' => [
                    'age' => [
                        'type' => 'long'
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testDoubleType()
    {
        $this->dummy->double('currency_value');

        $expected = [
            'mappings' => [
                'properties' =>
                    [
                        'currency_value' =>
                            [
                                'type' => 'double'
                            ]
                    ]
            ]
        ];

        $this->assertSame($expected,$this->dummy->schema);
    }

    public function testFloatType()
    {
        $this->dummy->float('value');

        $expected=[
            'mappings'=>[
                'properties'=>[
                    'value'=>[
                        'type'=>'float'
                    ]
                ]
            ]
        ];

        $this->assertSame($expected,$this->dummy->schema);
    }

    public function testTinyIntType()
    {
        $this->dummy->tinyInt('value');

        $expected=[
            'mappings'=>[
                'properties'=>
                [
                    'value'=>[
                        'type'=>'byte'
                    ]
                ]
            ]
        ];

        $this->assertSame($expected,$this->dummy->schema);
    }
}