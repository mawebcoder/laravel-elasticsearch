<?php

namespace Tests\Integration;


use Illuminate\Support\Arr;
use Mawebcoder\Elasticsearch\Models\Test;
use Tests\ElasticSearchIntegrationTestCase;

class UpdateElasticsearchDocumentsTest extends ElasticSearchIntegrationTestCase
{
    public function test_update_method_works_correctly_after_insert_update_immediately_with_id()
    {
        $model = new Test();

        $userDocument = $this->insertElasticDocument($model, [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ]);

        $this->assertEquals($userDocument->getAttributes(), [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ]);

        // run update method
        $updateResult = $userDocument->update(['name' => 'Ali Ghorbani', 'age' => 21]);
        $this->assertTrue($updateResult);

        sleep(1);

        $userAfterUpdate = $model->find(1);

        $expected = [
            'id' => 1,
            'name' => 'Ali Ghorbani',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 21
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }

    public function test_update_method_works_correctly_after_insert_then_find_and_update_with_id()
    {
        $model = new Test();

        $userDocument = $this->insertElasticDocument($model, [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ]);

        $this->assertEquals($userDocument->getAttributes(), [
            'id' => 1,
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ]);

        $userDocument = $model->find(1);

        // run update method
        $updateResult = $userDocument->update(['name' => 'ali']);
        $this->assertTrue($updateResult);

        sleep(1);

        $userAfterUpdate = $model->find(1);

        $expected = [
            'id' => 1,
            'name' => 'ali',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }

    public function test_update_method_works_correctly_after_insert_without_id()
    {
        $model = new Test();

        $userDocument = $this->insertElasticDocument($model, [
            'name' => 'Mohsen Souri',
            'is_active' => true,
            'details' => 'BackEnd TeamLead',
            'age' => 25
        ]);

        $idAfterInsert = $userDocument->id;

        // expect id because we don't know elasticsearch which id chosen for this document
        $this->assertEquals(Arr::except($userDocument->getAttributes(), 'id'), [
            'name' => 'Mohsen Souri',
            'is_active' => true,
            'details' => 'BackEnd TeamLead',
            'age' => 25
        ]);

        // run update method
        $updateResult = $userDocument->update([
            'name' => 'Morteza Babanezhad',
            'details' => 'BackEnd Developer',
            'age' => 32
        ]);

        $this->assertTrue($updateResult);

        sleep(1);

        $userAfterUpdate = $model->find($idAfterInsert);

        $expected = [
            'id' => $idAfterInsert,
            'name' => 'Morteza Babanezhad',
            'is_active' => true,
            'details' => 'BackEnd Developer',
            'age' => 32
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }

    public function test_update_method_works_correctly_after_insert_then_find_and_update_without_id()
    {
        $model = new Test();

        $userDocument = $this->insertElasticDocument($model, [
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ]);

        $idAfterInsert = $userDocument->id;

        $this->assertEquals(Arr::except($userDocument->getAttributes(), 'id'), [
            'name' => 'mohammad amiri',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ]);

        $userDocument = $model->find($idAfterInsert);

        // run update method
        $updateResult = $userDocument->update(['name' => 'ali']);
        $this->assertTrue($updateResult);

        sleep(1);

        $userAfterUpdate = $model->find($idAfterInsert);

        $expected = [
            'id' => $idAfterInsert,
            'name' => 'ali',
            'is_active' => true,
            'details' => 'this is test text',
            'age' => 30
        ];

        $this->assertEquals($expected, $userAfterUpdate->getAttributes());
    }
}