<?php

namespace Mawebcoder\Elasticsearch\Models;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use PHPUnit\Runner\FileDoesNotExistException;
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
     * @throws FieldNotDefinedInIndexException
     */
    public static function create(array $options): static
    {

        $object = new static();

        foreach ($options as $key => $value) {
            $object->{$key} = $value;
        }

        $object->save();

        return $object;
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public static function checkMapping(array $options): void
    {
        $fields = Elasticsearch::setModel(static::class)
            ->getFields();

        foreach ($options as $field => $option) {
            if (!in_array($field, $fields)) {
                throw new FieldNotDefinedInIndexException(
                    message: "field with name " . $field . "not defined in model index"
                );
            }
        }
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     */
    public function save(): static
    {
        static::checkMapping($this->attributes);

        Elasticsearch::setModel(static::class)->post(data: $this->attributes);

        return $this;
    }
}