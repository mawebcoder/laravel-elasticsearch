<?php

namespace Mawebcoder\Elasticsearch\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionException;

class ElasticApiService implements ElasticHttpRequestInterface
{
    public string $index;

    public Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public static array $migrationsPath = [];

    public Response $connection;

    public ?string $elasticModel = null;


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function post(?string $path = null, array $data = []): ResponseInterface
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        if (empty($data)) {
            return $this->client->post($path);
        }

        return $this->client->post($path, [RequestOptions::JSON => $data]);
    }


    /**
     * @param string|null $path
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function get(?string $path = null): ResponseInterface
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        return $this->client->get($path);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function put(?string $path = null, array $data = []): ResponseInterface
    {
        $path = trim($this->generateBaseIndexPath() . '/' . trim($path), '/');

        if (!empty($data)) {
            return $this->client->put($path, [
                'json' => $data
            ]);
        }

        return $this->client->put($path);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function delete(?string $path = null, array $data = []): ResponseInterface
    {
        $path = $this->generateBaseIndexPath() . '/' . trim($path);

        return $this->client->delete($path);
    }

    /**
     * @throws ReflectionException
     */
    public function generateBaseIndexPath(): string
    {
        if (isset($this->elasticModel)) {
            /**
             * @type BaseElasticsearchModel $elasticModelObject
             */
            $elasticModelObject = (new ReflectionClass($this->elasticModel))->newInstance();
        }


        $path = trim(
            config('elasticsearch.host') . ":" . config("elasticsearch.port")
        );

        if (isset($elasticModelObject)) {
            $path .= '/' . $elasticModelObject->getIndex();
        }

        return $path;
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
    public function loadMigrationsFrom(string $directory): void
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
     * @throws GuzzleException
     */
    public function dropModelIndex(): ResponseInterface
    {
        $fullPath = $this->generateBaseIndexPath();

        return $this->client->delete($fullPath);
    }


    /**
     * @throws GuzzleException
     */
    public function getAllIndexes(): array
    {
        $path = trim(config('elasticsearch.host') . ":" . config("elasticsearch.port"), '/') . '/_aliases';

        $response = $this->client->get($path);

        $result = json_decode($response->getBody(), true);

        return array_keys($result);
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function getFields(): array
    {
        $response = $this->get('_mapping');

        $index = (new ReflectionClass($this->elasticModel))->newInstance()->getIndex();

        $jsonResponse = json_decode($response->getBody(), true);

        if (array_key_exists('properties', $jsonResponse[$index]['mappings'])) {
            return array_keys($jsonResponse[$index]['mappings']['properties']);
        }

        return array_keys($jsonResponse[$index]['mappings']);
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function getMappings(): array
    {
        $response = $this->get('_mapping');

        $index = (new ReflectionClass($this->elasticModel))->newInstance()->getIndex();

        $jsonResponse = json_decode($response->getBody(), true);

        return $jsonResponse[$index]['mappings']['properties'];
    }


}
