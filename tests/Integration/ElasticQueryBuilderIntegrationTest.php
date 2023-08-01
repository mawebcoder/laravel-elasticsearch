<?php

namespace Tests\Integration;

use Throwable;
use ReflectionException;
use GuzzleHttp\Exception\GuzzleException;
use Tests\ElasticSearchIntegrationTestCase;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Models\Elasticsearch as elasticModel;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;

class ElasticQueryBuilderIntegrationTest extends ElasticSearchIntegrationTestCase
{
    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testFindMethod()
    {
        $data = [
            'id' => 2,
            'name' => 'mohammad',
            'age' => 23,
            'active' => true,
            'description' => 'description'
        ];

        $elasticModel = new elasticModel();


        $this->insertElasticDocument($elasticModel, $data);

        /**
         * elastic implements creating ,updating and deleting action as  asynchronous,
         * so we wait 2 seconds to be sure that elasticsearch added the data
         */
        sleep(2);

        $record = $this->elasticModel->find($data['id']);

        $this->assertEquals($data, $record->getAttributes());
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testSetNullForUndefinedMappedData()
    {
        $data = [
            'id' => 1,
            'description' => 'this is name'
        ];

        $elasticModel = new elasticModel();

        $this->insertElasticDocument($elasticModel, $data);

        /**
         * elastic implements creating ,updating and deleting action as  asynchronous,
         * so we wait 2 seconds to be sure that elasticsearch added the data
         */
        sleep(2);

        $result = $this->elasticModel->find($data['id']);

        $this->assertEquals(
            ['id' => 1, 'description' => 'this is name'],
            $result->attributes
        );
    }


    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testCanUpdateData()
    {
        $data = [
            'id' => 1,
            'is_active' => false,
            'name' => 'amir',
            'description' => 'this is test text'
        ];

        $this->insertElasticDocument(new elasticModel(), $data);

        /**
         * elastic implements creating ,updating and deleting action as  asynchronous,
         * so we wait 2 seconds to be sure that elasticsearch added the data
         */
        sleep(2);

        $model = elasticModel::newQuery()->find(1);

        $newData = [
            'name' => 'mohammad',
            'is_active' => true,
            'description' => 'new description'
        ];

        $model->update($newData);

        sleep(2);

        $model = elasticModel::newQuery()->find(1);

        $expectation = [
            'id' => 1,
            "is_active" => true,
            "name" => "mohammad",
            'description' => 'new description'
        ];

        $this->assertEquals($expectation, $model->getAttributes());
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function testCanDeleteDataByModelRecord()
    {
        $data = [
            BaseElasticsearchModel::FIELD_ID => 1,
            elasticModel::FILED_DESCRIPTION => 'this is description'
        ];

        $this->insertElasticDocument(new elasticModel(), $data);

        /**
         * elastic implements creating ,updating and deleting action as  asynchronous,
         * so we wait 2 seconds to be sure that elasticsearch added the data
         */
        sleep(2);

        $model = elasticModel::newQuery()->find($data['id']);

        $model->delete();

        sleep(2);

        $model = elasticModel::newQuery()->find(1);

        $this->assertEquals(null, $model);
    }


    /**
     * @return void
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws GuzzleException
     */
    public function testSelect()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->select(elasticModel::FILED_NAME, BaseElasticsearchModel::FIELD_ID)
            ->get();

        $firstResultAttributes = $results->first()->getAttributes();

        $this->assertEquals([elasticModel::FILED_NAME, BaseElasticsearchModel::FIELD_ID],
            array_keys($firstResultAttributes));
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testTake()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->take(1)
            ->get();

        $this->assertEquals(1, $results->count());
    }

    /**
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testOffset()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->offset(1)
            ->take(1)
            ->get();

        $this->assertEquals(2, $results->first()->getAttributes()['id']);
    }


    /**
     * @return void
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereEqualCondition()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->where('name', 'ali')
            ->select('name')
            ->get();

        $this->assertEquals(1, $results->count());

        $firstResult = $results->first()->attributes['name'];

        $this->assertEquals('ali', $firstResult);
    }


    /**
     * @throws RequestException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws GuzzleException
     */
    public function testWhereNotEqualCondition()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = $this->elasticModel
            ->where('name', '<>', 'ali')
            ->select('name')
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === 'ahmad'));

        $this->assertTrue($results->contains(fn($row) => $row->name === 'jafar'));
    }


    /**
     * @throws RequestException
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws SelectInputsCanNotBeArrayOrObjectException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testOrWhereCondition()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->where('name', 'ali')
            ->orWhere('name', 'jafar')
            ->select('name')
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === 'ali'));

        $this->assertTrue($results->contains(fn($row) => $row->name === 'jafar'));
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     * @throws GuzzleException
     */
    public function testWhereBetweenCondition()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->whereBetween('age', [22, 26])
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === 22));

        $this->assertTrue($results->contains(fn($row) => $row->age === 26));
    }

    /**
     * @throws RequestException
     * @throws WrongArgumentType
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws GuzzleException
     */
    public function testNotBetweenCondition()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->whereNotBetween('age', [22, 26])
            ->get();


        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === 30));
    }


    /**
     * @throws RequestException
     * @throws WrongArgumentType
     * @throws Throwable
     * @throws FieldNotDefinedInIndexException
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testOrBetweenCondition()
    {
        $data = $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->where('age', $data[1]->age)
            ->orWhereBetween('age', [$data[0]->age, $data[2]->age])
            ->get();

        $this->assertEquals(3, $results->count());

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === $data[0]->age));

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === $data[1]->age));

        $this->assertTrue($results->contains(fn($row) => intval($row->age) === $data[2]->age));
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws InvalidSortDirection
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testOrderByAsc()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->orderBy('age')
            ->get();

        $first = $results->first();

        $second = $results[1];

        $third = $results[2];

        $this->assertEquals(22, $first->age);

        $this->assertEquals(26, $second->age);

        $this->assertEquals(30, $third->age);
    }

    /**
     * @throws InvalidSortDirection
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function testOrderByDesc()
    {
        $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->orderBy('age', 'desc')
            ->get();

        $first = $results->first();

        $second = $results[1];

        $third = $results[2];

        $this->assertEquals(30, $first->age);

        $this->assertEquals(26, $second->age);

        $this->assertEquals(22, $third->age);
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testWhereTerm()
    {
        $data = $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->whereTerm('name', $data[0]->name)
            ->get();

        $this->assertEquals(1, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[0]->name));
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testOrWhereTerm()
    {
        $data = $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->where('name', $data[0]->name)
            ->orWhereTerm('name', $data[1]->name)
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[0]->name));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->name));
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function testWhereNotTerm()
    {
        $data = $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->whereTerm('name', '<>', $data[0]->name)
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->name));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[2]->name));
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function testOrWhereEqual()
    {
        $data = $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->where(elasticModel::FILED_NAME, $data[0]->name)
            ->orWhere(elasticModel::FILED_NAME, $data[1]->name)
            ->get();

        $this->assertEquals(2, $results->count());

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[0]->name));

        $this->assertTrue($results->contains(fn($row) => $row->name === $data[1]->name));
    }


    /**
     * @throws FieldNotDefinedInIndexException
     * @throws RequestException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function testWhereGreaterThan()
    {
        $data = $this->registerSomeRecords();

        sleep(2);

        $results = elasticModel::newQuery()
            ->where('age', '>', $data[0]->age)
            ->get();

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains(fn($row) => $row->age === $data[1]->age));

        $this->assertTrue($results->contains(fn($row) => $row->age === $data[2]->age));
    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws RequestException
//     * @throws ReflectionException
//     */
//    public function testOrWhereGreaterThan()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'first',
//            'details' => 'number one'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'second',
//            'details' => 'number 2'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', 9)
//            ->orWhere('age', '>', 9)
//            ->get();
//
//        $this->assertEquals(2, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 19));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 9));
//    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testWhereLessThan()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'first',
//            'details' => 'number one'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'second',
//            'details' => 'number 2'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', '<', 19)
//            ->get();
//
//        $this->assertEquals(1, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 9));
//    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testOrWhereLessThan()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'first',
//            'details' => 'number one'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'second',
//            'details' => 'number 2'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', 19)
//            ->orWhere('age', '<', 19)
//            ->get();
//
//        $this->assertEquals(2, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 9));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 19));
//    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testWhereLike()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('details', 'like', 'to be hap')
//            ->get();
//
//        $this->assertEquals(1, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->details === $data2['details']));
//    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testWhereNotLike()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('details', 'not like', 'to be hap')
//            ->get();
//
//        $this->assertEquals(1, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->details === $data['details']));
//    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testOrWhereLike()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', 19)
//            ->orWhere('details', 'like', 'to be hap')
//            ->get();
//
//        $this->assertEquals(2, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->details === $data2['details']));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === $data['age']));
//    }
//
//    /**
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testWhereGreaterThanOrEqual()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $data3 = [
//            'id' => 3,
//            'age' => 21,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        $this->elasticModel->create($data3);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', '>=', 19)
//            ->get();
//
//        $this->assertEquals(2, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 19));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 21));
//    }
//
//    /**
//     * @throws RequestException
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     */
//    public function testOrWhereGreaterThanOrEqual()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $data3 = [
//            'id' => 3,
//            'age' => 21,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        $this->elasticModel->create($data3);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', 9)
//            ->orWhere('age', '>=', 21)
//            ->get();
//
//        $this->assertEquals(2, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 9));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 21));
//    }
//
//    /**
//     * @throws RequestException
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     */
//    public function testWhereLessThanOrEqual()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $data3 = [
//            'id' => 3,
//            'age' => 21,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        $this->elasticModel->create($data3);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', '<=', 19)
//            ->get();
//
//        $this->assertEquals(2, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 19));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 9));
//    }
//
//    /**
//     * @throws RequestException
//     * @throws FieldNotDefinedInIndexException
//     * @throws ReflectionException
//     */
//    public function testOrWhereLessThanOrEqual()
//    {
//        $data = [
//            'id' => 1,
//            'age' => 19,
//            'name' => 'mohammad',
//            'details' => 'he studied at line school'
//        ];
//
//        $data2 = [
//            'id' => 2,
//            'age' => 9,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $data3 = [
//            'id' => 3,
//            'age' => 21,
//            'name' => 'narges',
//            'details' => 'she wants to be happy with other people'
//        ];
//
//        $this->elasticModel->create($data);
//
//        $this->elasticModel->create($data2);
//
//        $this->elasticModel->create($data3);
//
//        sleep(2);
//
//        $results = $this->elasticModel
//            ->where('age', 9)
//            ->orWhere('age', '<=', 21)
//            ->get();
//
//        $this->assertEquals(3, $results->count());
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 19));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 9));
//
//        $this->assertTrue($results->contains(fn($row) => $row->age === 21));
//    }
//
//
//    /**
//     * @throws ReflectionException
//     * @throws RequestException
//     */
//    public function testGetMappings()
//    {
//        $mappings = $this->elasticModel->getMappings();
//
//        $expected = [
//            "age" => [
//                'type' => 'integer'
//            ],
//            "details" => [
//                "type" => 'text'
//            ],
//            "id" => [
//                "type" => "integer"
//            ],
//            "is_active" => [
//                "type" => "boolean"
//            ],
//            "name" => [
//                "type" => "keyword"
//            ]
//        ];
//
//        $this->assertSame($expected, $mappings);
//    }
}