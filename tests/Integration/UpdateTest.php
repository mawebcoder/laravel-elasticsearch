<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use ReflectionException;
use Tests\DummyRequirements\Models\EUserModel;
use Tests\TestCase\Integration\BaseIntegrationTestCase;


class UpdateTest extends BaseIntegrationTestCase
{

    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function test_update_method_works_correctly_after_insert_update_immediately_with_id(): void
    {
        $model = new EUserModel();

        $userDocument = $this->insertElasticDocument($model, [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'age' => 30
        ]);


        $updateResult = $userDocument->mustBeSync()
            ->update(['name' => 'Ali Ghorbani', 'age' => 21]);

        $this->assertTrue($updateResult);

        $userAfterUpdate = $model->find(1);

        $expected = [
            'id' => 1,
            'name' => 'Ali Ghorbani',
            'is_active' => true,
            'age' => 21,
            'description' => null,
            'information'=>null
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }


    /**
     * @throws RequestException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function test_update_method_works_correctly_after_insert_then_find_and_update_with_id():void
    {
        $model = new EUserModel();

        $userDocument = $this->insertElasticDocument($model, [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'age' => 30
        ]);


        $this->assertEquals([
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'age' => 30,
            'description' => null,
            'information'=>null
        ], $userDocument->getAttributes());

        $userDocument = $model->find(1);

        // run update method
        $updateResult = $this->update($userDocument, ['name' => 'ali']);
        $this->assertTrue($updateResult);

        $userAfterUpdate = $model->find(1);

        $expected = [
            'id' => 1,
            'name' => 'ali',
            'is_active' => true,
            'age' => 30,
            'description' => null,
            'information'=>null
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws RequestException
     * @throws JsonException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function test_update_method_works_correctly_after_insert_without_id(): void
    {
        $model = new EUserModel();

        $userDocument = $this->insertElasticDocument($model, [
            'name' => 'Mohsen Souri',
            'is_active' => true,
            'age' => 25
        ]);

        $idAfterInsert = $userDocument->id;

        // expect id because we don't know elasticsearch which id chosen for this document
        $this->assertEquals([
            'name' => 'Mohsen Souri',
            'is_active' => true,
            'age' => 25,
            'description' => null,
            'information'=>null
        ], Arr::except($userDocument->getAttributes(), 'id'));

        // run update method
        $updateResult = $this->update($userDocument, [
            'name' => 'Mohsen Souri',
            'is_active' => true,
            'age' => 25
        ]);

        $this->assertTrue($updateResult);

        $userAfterUpdate = $model->find($idAfterInsert);

        $expected = [
            'id' => $idAfterInsert,
            'name' => 'Mohsen Souri',
            'is_active' => true,
            'age' => 25,
            'description' => null,
            'information'=>null
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }


    /**
     * @throws RequestException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function test_update_method_works_correctly_after_insert_then_find_and_update_without_id(): void
    {
        $model = new EUserModel();

        $userDocument = $this->insertElasticDocument($model, [
            'name' => 'mohammad amiri',
            'is_active' => true,
            'age' => 30
        ]);

        $idAfterInsert = $userDocument->id;

        $this->assertEquals([
            'name' => 'mohammad amiri',
            'is_active' => true,
            'age' => 30,
            'description' => null,
            'information'=>null
        ], Arr::except($userDocument->getAttributes(), 'id'));

        $userDocument = $model->find($idAfterInsert);

        // run update method
        $updateResult = $this->update($userDocument, ['name' => 'ali']);
        $this->assertTrue($updateResult);

        $userAfterUpdate = $model->find($idAfterInsert);

        $expected = [
            'id' => $idAfterInsert,
            'name' => 'ali',
            'is_active' => true,
            'age' => 30,
            'description' => null,
            'information'=>null
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }
}