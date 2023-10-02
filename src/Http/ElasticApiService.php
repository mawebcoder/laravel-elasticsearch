<?php

namespace Mawebcoder\Elasticsearch\Http;


use Couchbase\IndexNotFoundException;
use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\ModelNotDefinedException;
use ReflectionClass;
use GuzzleHttp\Client;
use ReflectionException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;

class ElasticApiService implements ElasticHttpRequestInterface
{
    public string $index;

    public Client $client;
    public bool $isTempIndex = false;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setTempIndex(): static
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
    public function post(?string $path = null, string|array $data = [], bool $mustBeSync = false): ResponseInterface
    {
        $path = $this->buildPath($path, $mustBeSync);

        if (empty($data)) {
            return $this->client->post($path);
        }

        $this->refreshTempIndex();

        $options = [
            RequestOptions::AUTH => [config('elasticsearch.username'), config('elasticsearch.password')],
            RequestOptions::HEADERS => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ];

        // sure is user need to pass the whole request body (like NDJSON format used for bulk insert)
        // or use the JSON Format!
        $options[is_array($data) ? RequestOptions::JSON : RequestOptions::BODY] = $data;

        return $this->client->post($path, $options);
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function head(?string $path = null, array $data = [], bool $mustBeSync = false): ResponseInterface
    {
        $path = $this->buildPath($path, $mustBeSync);
        $path = trim($path, '/');


        if (empty($data)) {
            return $this->client->head($path);
        }

        $this->refreshTempIndex();
        return $this->client->head(
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
    public function put(?string $path = null, array $data = [], bool $mustBeSync = false): ResponseInterface
    {
        // append the prefix feature that package provide
        if ($this->isTempIndex) {
            $path = trim($this->generateBaseIndexPath() . trim($path, '/'), '/');
        } else {
            $path = trim($this->generateBaseIndexPath() . '/' . trim($path, '/'), '/');
        }

        $parts = parse_url($path);

        if ($mustBeSync) {
            if (isset($parts['query'])) {
                $path .= '&refresh=true';
            } else {
                $path .= '?refresh=true';
            }
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
    public function delete(?string $path = null, array $data = [], bool $mustBeSync = false): bool
    {
        if ($this->isTempIndex) {
            $path = trim($this->generateBaseIndexPath() . trim($path), '/');
        } else {
            $path = trim($this->generateBaseIndexPath() . '/' . trim($path), '/');
        }

        $parts = parse_url($path);

        if ($mustBeSync) {
            /**
             * if already some query params exists in path
             */
            if (isset($parts['query'])) {
                $path .= '&refresh=true';
            } else {
                $path .= '?refresh=true';
            }
        }

        $this->refreshTempIndex();

        $response = $this->client->delete($path, [
            'auth' => [config('elasticsearch.username'), config('elasticsearch.password')]
        ]);

        return json_decode($response->getBody(), true)['acknowledged'] ?? false;
    }

    public function refreshTempIndex(): void
    {
        $this->isTempIndex = false;
    }


    public function getIndexNameWithPrefix(): string
    {
        /**
         * @type BaseElasticsearchModel $elasticModelObject
         */

        if (!isset($this->elasticModel)) {
            throw new \Exception("You must set the model then call this method!");
        }

        $elasticModelObject = (new ReflectionClass($this->elasticModel))->newInstance();

        $indexName = $elasticModelObject->getIndex();

        if ($prefix = config('elasticsearch.index_prefix')) {
            return $prefix . $indexName;
        }

        return $indexName;
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
     * @throws GuzzleException
     * @throws JsonException
     * @throws ModelNotDefinedException
     * @throws ReflectionException
     */
    public function dropModelIndex(): ResponseInterface|null
    {
        if (!$this->elasticModel) {
            throw new ModelNotDefinedException();
        }


        $model = new ReflectionClass($this->elasticModel);

        $model = $model->newInstance();

        $index = $model->getIndexWithPrefix();

        if (!$this->hasIndex($index)) {
            return null;
        }

        $fullPath = $this->generateBaseIndexPath();

        return $this->client->delete($fullPath);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function hasIndex(string $index): bool
    {
        $allIndices = $this->getAllIndexes();

        return in_array($index, $allIndices, true);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getAllIndexes(): array
    {
        $path = trim(config('elasticsearch.host') . ":" . config("elasticsearch.port"), '/') . '/_aliases';

        $response = $this->client->get($path);

        $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

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

    /**
     * @param string|null $path
     * @param bool $mustBeSync
     * @return string
     * @throws ReflectionException
     */
    public function buildPath(?string $path, bool $mustBeSync): string
    {
        if ($this->isTempIndex) {
            $path = $this->generateBaseIndexPath() . trim($path);
        } else {
            $path = $this->generateBaseIndexPath() . '/' . trim($path);
        }

        $parts = parse_url($path);

        $mustBeSync && $path .= isset($parts['query']) ? '&refresh=true' : '?refresh=true';
        return $path;
    }
}
