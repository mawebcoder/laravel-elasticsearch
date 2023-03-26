<?php

namespace Mawebcoder\Elasticsearch\Http;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use ReflectionClass;
use ReflectionException;

class ElasticHttpRequest implements ElasticHttpRequestInterface
{

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

    public function setModel(string $modelName)
    {
        $this->elasticModel = $modelName;
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
    public function put(?string $path = null): Response
    {
        $path = $this->generateFullPath($path);

        $response = Http::delete($path);

        $response->throw();

        return $response;
    }

}