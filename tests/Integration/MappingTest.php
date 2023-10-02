<?php

namespace Tests\Integration;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\ModelNotDefinedException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use ReflectionException;
use Tests\DummyRequirements\Models\EUserModel;
use Tests\TestCase\Integration\BaseIntegrationTestCase;

class MappingTest extends BaseIntegrationTestCase
{

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testCheckMappingAllowRightOptionsToContinue(): void
    {
        $elasticsearch = new EUserModel();

        $options = [
            'description' => 'hoho'
        ];

        $elasticsearch->checkMapping($options);

        $this->assertTrue(true);
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testCheckMappingDoesNotAllowWrongOptionsToContinue(): void
    {
        $elasticsearch = new EUserModel();

        $options = [
            'descriptions' => 'hoho'
        ];

        $this->withoutExceptionHandling();

        $this->expectException(Exception::class);

        $this->expectExceptionMessage('field with name descriptions not defined in model index');

        $elasticsearch->checkMapping($options);
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testCheckMappingAllowRightOptionsToContinueForNestedOptions(): void
    {
        $elasticsearch = new EUserModel();

        $options = ['information.age' => 'value'];

        $elasticsearch->checkMapping($options);

        $this->assertTrue(true);
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testCheckMappingDoesNotAllowWrongOptionsToContinueForNestedOptions(): void
    {
        $elasticsearch = new EUserModel();

        $options = ['information.ali' => 'value'];

        $this->withoutExceptionHandling();

        $this->expectException(FieldNotDefinedInIndexException::class);

        $this->expectExceptionMessage('field with name ali does not exist in index mapping');

        $elasticsearch->checkMapping($options);
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testCheckMappingDoesNotAllowWrongOptionsToContinueForNestedOptionsInParentLevel(): void
    {
        $elasticsearch = new EUserModel();

        $options = ['informations.ali' => 'value'];

        $this->withoutExceptionHandling();

        $this->expectException(FieldNotDefinedInIndexException::class);

        $this->expectExceptionMessage('field with name informations not defined in model index');

        $elasticsearch->checkMapping($options);
    }

    public function testCheckRecursiveGetKeys(): void
    {
        $array = [
            'information' => [
                'names' => [
                    [
                        'age' => 10
                    ],
                    [
                        'age' => 20
                    ]
                ],
                'values' => [
                    'key' => [
                        'ali' => [
                            'age' => 'value'
                        ]
                    ]
                ]
            ]
        ];


        $elasticsearch = new EUserModel();

        $result = $elasticsearch->arrayKeysRecursiveAsFlat($array);

        $this->assertEquals([
            "information",
            "names",
            0,
            "age",
            1,
            "age",
            "values",
            "key",
            "ali",
            "age"
        ], $result);
    }

    public function testCheckCanGetAllIndices(): void
    {
        $indices = Elasticsearch::getAllIndexes();

        $this->assertIsArray($indices);
    }

}