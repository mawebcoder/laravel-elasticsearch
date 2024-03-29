<?php

namespace Tests\Unit;

use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase;

use Tests\DummyRequirements\Models\EUserModel;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase\Integration\Traits\HasFakeMigration;
use Mawebcoder\Elasticsearch\Exceptions\FieldNameException;
use Mawebcoder\Elasticsearch\Exceptions\InvalidAnalyzerType;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;

class CreateMigrationsTest extends TestCase
{
    use CreatesApplication;
    use HasFakeMigration;
    use WithoutMiddleware;

    public BaseElasticMigration $dummy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummy = require $this->getMigrationPathByModel(EUserModel::class);

        $baseMigrationMock = $this->getMockBuilder(BaseElasticMigration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $baseMigrationMock
            ->method('isCreationState')
            ->willReturn(true);
    }


    public function testIntegerFieldType():void
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

    public function testBooleanType():void
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

    public function testSmallIntegerType():void
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

    public function testBigIntType():void
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

    public function testDoubleType():void
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

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testFloatType():void
    {
        $this->dummy->float('value');

        $expected = [
            'mappings' => [
                'properties' => [
                    'value' => [
                        'type' => 'float'
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testTinyIntType():void
    {
        $this->dummy->tinyInt('value');

        $expected = [
            'mappings' => [
                'properties' =>
                    [
                        'value' => [
                            'type' => 'byte'
                        ]
                    ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testStringType():void
    {
        $this->dummy->string('name');

        $expected = [
            'mappings' => [
                'properties' =>
                    [
                        'name' => [
                            'type' => 'keyword'
                        ]
                    ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    /**
     * @throws InvalidAnalyzerType
     */
    public function testTextType():void
    {
        $this->dummy->text('body');

        $expected = [
            'mappings' =>
                [
                    'properties' =>
                        [
                            'body' => [
                                'type' => 'text'
                            ]
                        ]
                ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testDateTimeType():void
    {
        $this->dummy->datetime('created_at');

        $expected = [
            'mappings' =>
                [
                    'properties' =>
                        [
                            'created_at' => [
                                'type' => 'date'
                            ]
                        ]
                ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    /**
     * @throws FieldNameException
     */
    public function test_object_type():void
    {
        $this->dummy->object('categories', [
            'name' => BaseElasticMigration::TYPE_OBJECT,
            'description' => BaseElasticMigration::TYPE_TEXT,
            'age' => BaseElasticMigration::TYPE_INTEGER
        ]);

        $expected = [
            'mappings' => [
                'properties' =>
                    [
                        "categories" => [
                            "type" => 'object',
                            "properties" => [
                                "name" => [
                                    "type" => "object"
                                ],
                                "description" => [
                                    "type" => 'text'
                                ],
                                "age" => [
                                    'type' => 'integer'
                                ]
                            ]
                        ]
                    ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    /**
     * @throws InvalidAnalyzerType
     */
    public function testFieldData():void
    {
        $this->dummy->text('text', true);

        $expected = [
            'mappings' => [
                'properties' =>
                    [
                        'text' => [
                            'type' => 'text',
                            'fielddata' => true
                        ]
                    ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testFieldDataOnObjects():void
    {
        $this->dummy->object('categories', [
            'name' => [
                'type' => 'text',
                'fielddata' => true
            ]
        ]);

        $expected = [
            'mappings' => [
                'properties' => [
                    'categories' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'text',
                                'fielddata' => true
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $this->dummy->schema);
    }


}