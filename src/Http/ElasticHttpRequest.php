<?php

namespace Mawebcoder\Elasticsearch\Http;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use ReflectionClass;
use ReflectionException;

class ElasticHttpRequest implements ElasticHttpRequestInterface
{

    public static array $migrationsPath = [];

    public Response $connection;

    public string $elasticModel;

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function post(?string $path = null, array $data = []): Response
    {
        $path = $this->generateFullPath($path);

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
        $path = $this->generateFullPath($path);

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
        $path = $this->generateFullPath($path);

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
        $path = $this->generateFullPath($path);

        $response = Http::delete($path, $data);

        $response->throw();

        return $response;
    }

    /**
     * @throws ReflectionException
     */
    private function generateFullPath(?string $path = null): string
    {
        /**
         * @type Elasticsearch $elasticModelObject
         */
        $elasticModelObject = new ReflectionClass($this->elasticModel);

        $fullPath = trim(config('elasticsearch')) . '/' . $elasticModelObject->getIndex() . '/_doc';

        if ($path) {
            $fullPath .= '/' . $path;
        }

        return $fullPath;
    }

    public function setModel(string $modelName): static
    {
        $this->elasticModel = $modelName;
        return $this;
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

}