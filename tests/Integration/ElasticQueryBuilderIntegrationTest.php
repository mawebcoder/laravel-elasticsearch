<?php

namespace Tests\Integration;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mawebcoder\Elasticsearch\ElasticsearchServiceProvider;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use Orchestra\Testbench\TestCase;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch as ElasticFacade;


class ElasticQueryBuilderIntegrationTest extends TestCase
{
   protected Elasticsearch $elastic;

   protected function setUp(): void
   {
       parent::setUp();

       $this->elastic = new Elasticsearch();

//       ElasticFacade::loadMigrationsFrom(__DIR__.'/../../src/Migration');
//
//       Artisan::call('migrate');
//       Artisan::call('elastic:migrate');
   }

   protected function tearDown(): void
   {
       parent::tearDown();

//       Artisan::call('elastic:migrate --reset');
   }

    protected function getPackageProviders($app): array
    {
        return [
          ElasticsearchServiceProvider::class,
        ];
   }


    /**
     * @throws RequestException
     * @throws \ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function test_elastic_create(): void
    {
        $options = [
            'id' => 1,
            'name' => 'test',
            'is_active' => true,
            'details' => 'bla bla bla lorem ipsum'
        ];

        $result = $this->elastic->create($options);

        $attributes = $result->attributes;

        $this->assertInstanceOf(Elasticsearch::class, $result);
        $this->assertObjectHasProperty('attributes', $result);

        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('is_active', $attributes);
        $this->assertArrayHasKey('details', $attributes);

    }

    /**
     * @throws RequestException
     * @throws \ReflectionException
     */
    public function test_elastic_create_undefined_field(): void
    {
        $undefined_field = 'title';
        $options = [
            'id' => 1,
            $undefined_field => 'test',
        ];

        $this->expectException(FieldNotDefinedInIndexException::class);
        $this->expectExceptionMessage("field with name " . $undefined_field . " not defined in model index");

        $this->elastic->create($options);
    }

    /**
     * @throws FieldNotDefinedInIndexException
     * @throws \ReflectionException
     * @throws RequestException
     */
    public function test_elastic_update(): void
    {
        try
        {
            $options = [
                'id' => 1,
                'name' => 'test',
                'is_active' => true,
                'details' => 'bla bla bla lorem ipsum'
            ];

            $model = $this->elastic->create($options);

            $options = [
                'id' => 1,
                'name' => 'updated',
                'is_active' => false,
            ];

            $model->update($options);

        } finally
        {
            $updated = $this->elastic->find(1)->first();
        }


        $attributes = $updated->attributes['hits']['hits'];//dd($attributes);

        $this->assertInstanceOf(Elasticsearch::class, $updated);
        $this->assertObjectHasProperty('attributes', $updated);

        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('is_active', $attributes);
        $this->assertArrayHasKey('details', $attributes);

    }
}