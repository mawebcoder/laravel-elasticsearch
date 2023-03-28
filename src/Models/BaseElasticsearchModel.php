<?php

namespace Mawebcoder\Elasticsearch\Models;

use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use ReflectionException;

abstract class BaseElasticsearchModel
{

    public array $attributes = [];

    public array $search = [
        "query" => [
            "sort" => [],
            "bool" => [
                "must" => [
                ],
            ]
        ]
    ];

    abstract public function getIndex(): string;


    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->attributes[$name];
    }


    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public static function create(array $options): static
    {
        $object = new static();

        foreach ($options as $key => $value) {
            $object->{$key} = $value;
        }

        $object->save($options);

        return $object;
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function save(array $values): static
    {
        Elasticsearch::setModel(static::class)->post(data: $values);

        return $this;
    }
}