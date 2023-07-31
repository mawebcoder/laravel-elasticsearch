<?php

namespace Mawebcoder\Elasticsearch\Http;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionException;

class ElasticApiService implements ElasticHttpRequestInterface
{
    public string $index;

    public Client $client;
    public bool $isTempIndex = false;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function isTempIndex(): static
    {
        $this->isTempIndex = true;
        return $this;
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
        if ($this->isTempIndex) {
            $path = $this->generateBaseIndexPath() . trim($path);
        } else {
            $path = $this->generateBaseIndexPath() . '/' . trim($path);
        }

        if (empty($data)) {
            return $this->client->post($path);
        }

        $this->refreshTempIndex();
        return $this->client->post(
            $path,
            [
                RequestOptions::JSON => $data,
                'auth' => [config('elasticsearch.username'), config('elasticsearch.password')]
            ]
        );
    }


    /**
     * @param string|null $path
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function get(?string $path = null): ResponseInterface
    {
        if ($this->isTempIndex) {
            $path = $this->generateBaseIndexPath() . trim($path);
        } else {
            $path = $this->generateBaseIndexPath() . '/' . trim($path);
        }

        $this->refreshTempIndex();

        return $this->client->get($path, [
            'auth' => [config('elasticsearch.username'), config('elasticsearch.password')]
        ]);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function put(?string $path = null, array $data = []): ResponseInterface
    {
        if ($this->isTempIndex) {
            $path = trim($this->generateBaseIndexPath() . trim($path), '/');
        } else {
            $path = trim($this->generateBaseIndexPath() . '/' . trim($path), '/');
        }


        if (!empty($data)) {
            return $this->client->put($path, [
                'json' => $data,
                'auth' => [config('elasticsearch.username'), config('elasticsearch.password')]
            ]);
        }

        $this->refreshTempIndex();

        return $this->client->put($path);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function delete(?string $path = null, array $data = []): ResponseInterface
    {
        if ($this->isTempIndex) {
            $path = $this->generateBaseIndexPath() . trim($path);
        } else {
            $path = $this->generateBaseIndexPath() . '/' . trim($path);
        }

        $this->refreshTempIndex();

        return $this->client->delete($path, [
            'auth' => [config('elasticsearch.username'), config('elasticsearch.password')]
        ]);
    }

    public function refreshTempIndex(): void
    {
        $this->isTempIndex = false;
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

        if ($this->isTempIndex || $this->elasticModel) {
            $path .= '/';

            if (config('elasticsearch.index_prefix')) {
                $path .= config('elasticsearch.index_prefix');
            }
        }

        if (isset($elasticModelObject)) {
            $path .= $elasticModelObject->getIndex();
        }

        return $path;
    }

    public function setModel(?string $modelName): static
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

        $this->refreshTempIndex();
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
        if (isset($result['.tasks'])) {
            unset($result['..tasks']);
        }
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
        $index = config('elasticsearch.index_prefix') . $index;
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

        $index = config('elasticsearch.index_prefix') . $index;

        $jsonResponse = json_decode($response->getBody(), true);

        return $jsonResponse[$index]['mappings']['properties'];
    }


}
