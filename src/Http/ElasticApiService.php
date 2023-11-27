<?php

namespace Mawebcoder\Elasticsearch\Http;


use JsonException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
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
use RuntimeException;

class ElasticApiService implements ElasticHttpRequestInterface
{
    public string $index;

    public const URI_SEPARATOR = '/';
    public Client $client;
    public bool $isTempIndex = false;

    public function __construct()
    {
        $this->client = new Client(['verify' => config('elasticsearch.ssl')]);
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
     * @throws IndexNamePatternIsNotValidException
     */
    public function post(?string $path = null, string|array $data = [], bool $mustBeSync = false): ResponseInterface
    {

        $path = $this->getUrl($path, $mustBeSync);


        if (empty($data)) {
            return $this->client->post($path, [
                RequestOptions::AUTH => $this->getCredentials()
            ]);
        }

        $this->refreshTempIndex();

        $options = [
            RequestOptions::AUTH => $this->getCredentials(),
            RequestOptions::HEADERS => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ];

        $options[is_array($data) ? RequestOptions::JSON : RequestOptions::BODY] = $data;


        return $this->client->post($path, $options);
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     */
    public function head(?string $path = null, array $data = [], bool $mustBeSync = false): ResponseInterface
    {
        $path = $this->getUrl($path, $mustBeSync);

        if (empty($data)) {
            return $this->client->head($path, [
                RequestOptions::AUTH => $this->getCredentials()
            ]);
        }

        $this->refreshTempIndex();

        return $this->client->head(
            $path,
            [
                RequestOptions::JSON => $data,
                RequestOptions::AUTH => $this->getCredentials()
            ]
        );
    }

    /**
     * @param string|null $path
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws IndexNamePatternIsNotValidException
     */
    public function get(?string $path = null): ResponseInterface
    {
        $path = $this->getUrl($path, false);

        $this->refreshTempIndex();

        return $this->client->get($path, [
            RequestOptions::AUTH => $this->getCredentials()
        ]);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     */
    public function put(?string $path = null, array $data = [], bool $mustBeSync = false): ResponseInterface
    {
        $path = $this->getUrl($path, $mustBeSync);

        if (!empty($data)) {
            return $this->client->put($path, [
                RequestOptions::JSON => $data,
                RequestOptions::AUTH => $this->getCredentials()
            ]);
        }

        $this->refreshTempIndex();

        return $this->client->put($path, [
            RequestOptions::AUTH => $this->getCredentials()
        ]);
    }


    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function delete(?string $path = null, array $data = [], bool $mustBeSync = false): bool
    {
        $path = $this->getUrl($path, $mustBeSync);

        $this->refreshTempIndex();

        $response = $this->client->delete($path, [
            RequestOptions::AUTH => $this->getCredentials()
        ]);

        return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)['acknowledged'] ?? false;
    }


    public function refreshTempIndex(): void
    {
        $this->isTempIndex = false;
    }

    /**
     * @throws ReflectionException
     * @throws IndexNamePatternIsNotValidException
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
            $index = $elasticModelObject->getIndexWithPrefix();

            $path .= self::URI_SEPARATOR;

            $path .= $index;

            return $path;
        }

        if ($this->isTempIndex) {
            return $this->getTempIndexUrl($path);
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
     * @throws IndexNamePatternIsNotValidException
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

        $fullPath = $this->getUrl('', false);

        return $this->client->delete($fullPath);
    }


    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function dropIndexByName(string $index): ?ResponseInterface
    {
        if (!$this->hasIndex($index)) {
            return null;
        }

        $path = trim(
                config('elasticsearch.host') . ":" . config("elasticsearch.port")
            ) . '/' . $index;

        $options = [
            RequestOptions::AUTH => $this->getCredentials()
        ];

        return $this->client->delete($path, $options);
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
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException|JsonException
     */
    public function getFields(): array
    {
        $response = $this->get('_mapping');

        $index = (new ReflectionClass($this->elasticModel))->newInstance()->getIndexWithPrefix();

        $jsonResponse = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (array_key_exists('properties', $jsonResponse[$index]['mappings'])) {
            return array_keys($jsonResponse[$index]['mappings']['properties']);
        }

        return array_keys($jsonResponse[$index]['mappings']);
    }

    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws JsonException
     */
    public function getMappings(): array
    {
        $response = $this->get('_mapping');

        $index = (new ReflectionClass($this->elasticModel))->newInstance()->getIndexWithPrefix();

        $jsonResponse = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $jsonResponse[$index]['mappings']['properties'];
    }

    /**
     * @param string|null $path
     * @param bool $mustBeSync
     * @return string
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     */
    public function getUrl(?string $path, bool $mustBeSync): string
    {
        if ($this->isTempIndex) {
            $path = trim($this->generateBaseIndexPath() . trim($path, self::URI_SEPARATOR), self::URI_SEPARATOR);
        } else {
            $path = trim(
                $this->generateBaseIndexPath() . self::URI_SEPARATOR . trim($path, self::URI_SEPARATOR),
                self::URI_SEPARATOR
            );
        }

        $parts = parse_url($path);

        if ($mustBeSync) {
            if (isset($parts['query'])) {
                $path .= '&refresh=true';
            } else {
                $path .= '?refresh=true';
            }
        }
        return $path;
    }

    /**
     * @return array
     */
    public function getCredentials(): array
    {
        return [config('elasticsearch.username'), config('elasticsearch.password')];
    }

    /**
     * @param string $path
     * @return string
     */
    public function getTempIndexUrl(string $path): string
    {
        $path .= self::URI_SEPARATOR;

        if (config('elasticsearch.index_prefix')) {
            $path .= config('elasticsearch.index_prefix');
        }
        return $path;
    }
}
