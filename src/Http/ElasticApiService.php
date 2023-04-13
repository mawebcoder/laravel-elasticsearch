<?php

namespace Mawebcoder\Elasticsearch\Http;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use ReflectionClass;
use ReflectionException;

class ElasticApiService implements ElasticHttpRequestInterface
{
    public string $index;

    public static array $migrationsPath = [];

    public Response $connection;

    public string $elasticModel;

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function post(?string $path = null, array $data = []): Response
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        $response = Http::post($path, $data);

        $response->throw();

        return $response;
    }


    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function get(?string $path = null): Response
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        $response = Http::get($path);

        $response->throw();

        return $response;
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function put(?string $path = null, array $data = []): Response
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        $response = Http::put($path, $data);

        $response->throw();

        return $response;
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function delete(?string $path = null, array $data = []): Response
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        $response = Http::delete($path, $data);

        $response->throw();

        return $response;
    }

    /**
     * @throws ReflectionException
     */
    private function generateBaseIndexPath(): string
    {
        /**
         * @type BaseElasticsearchModel $elasticModelObject
         */
        $elasticModelObject = (new ReflectionClass($this->elasticModel))->newInstance();

        return trim(
                config('elasticsearch.host') . ":" . config("elasticsearch.port")
            ) . '/' . $elasticModelObject->getIndex();
    }

    public function setModel(string $modelName): static
    {
        $this->elasticModel = $modelName;
        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public function getIndex(): string
    {
        /**
         * @type BaseElasticsearchModel $modelObject
         */
        $modelObject = (new ReflectionClass($this->elasticModel))->newInstance();

        return $modelObject->getIndex();
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function loadMigrationsFrom(string $directory)
    {
        if (!is_dir($directory)) {
            throw new DirectoryNotFoundException(message: 'directory ' . $directory . ' not found');
        }

        self::$migrationsPath = [
            ...self::$migrationsPath,
            ...[$directory]
        ];
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function dropModelIndex(): Response
    {
        $fullPath = $this->generateBaseIndexPath();

        $response = Http::delete($fullPath);

        $response->throw();

        return $response;
    }


    /**
     * @throws RequestException
     */
    public function getAllIndexes(): array
    {
        $path = trim(config('elasticsearch.host') . ":" . config("elasticsearch.port"), '/') . '/_aliases';

        $response = Http::get($path);

        $response->throw();

        $result = $response->json();

        return array_keys($result);
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function getFields(): array
    {
        $response = $this->get('_mapping');

        $index = (new ReflectionClass($this->elasticModel))->newInstance()->getIndex();

        $jsonResponse = $response->json();

        if (array_key_exists('properties', $jsonResponse[$index]['mappings']))
        {
            return array_keys($jsonResponse[$index]['mappings']['properties']);
        }

        return array_keys($jsonResponse[$index]['mappings']);
    }


}