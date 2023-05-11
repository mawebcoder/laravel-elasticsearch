<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mawebcoder\Elasticsearch\Exceptions\FieldNameException;
use Mawebcoder\Elasticsearch\Exceptions\InvalidAnalyzerType;
use Mawebcoder\Elasticsearch\Exceptions\NotValidFieldTypeException;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class CreateMigrationsTest extends TestCase
{
    use CreatesApplication;
    use WithoutMiddleware;

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

        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testFloatType()
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

    public function testTinyIntType()
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

    public function testStringType()
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
    public function testTextType()
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

    public function testDateTimeType()
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
    public function test_object_type()
    {
        $this->dummy->object('categories', [
            'name' => BaseElasticMigration::TYPE_OBJECT,
            'description' => BaseElasticMigration::TYPE_TEXT,
            'age' => BaseElasticMigration::TYPE_INTEGER
        ]);

        $expected = [
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

        ];
        $this->assertSame($expected, $this->dummy->schema);
    }

    /**
     * @throws InvalidAnalyzerType
     */
    public function testFieldData()
    {
        $this->dummy->text('text', true);

        $expected = [
            'properties' =>
                [
                    'text' => [
                        'type' => 'text',
                        'fielddata' => true
                    ]
                ]

        ];
        $this->assertSame($expected, $this->dummy->schema);
    }

    public function testFieldDataOnObjects()
    {
        $this->dummy->object('categories', [
            'name' => [
                'type' => 'text',
                'fielddata' => true
            ]
        ]);

        $expected = [
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
        ];

        $this->assertSame($expected,$this->dummy->schema);
    }


}