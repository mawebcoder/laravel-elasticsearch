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
    public function create(array $options): static
    {
        //@todo check if id exists so define costum id for it
        $object = new static();

        foreach ($options as $key => $value) {
            $object->{$key} = $value;
        }

        $object->save();

        return $object;
    }

    public function update(array $options)
    {
        //@todo
    }


    public function delete(array $options)
    {
        //@todo
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function find($id): static
    {
        $response = Elasticsearch::setModel(static::class)
            ->get("_doc/" . $id . '/_source');

        $response->throw();

        $result = $response->json();

        $object = new static();

        foreach ($result as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }

    public function first()
    {
    }

    public function get()
    {
    }


    public function where()
    {
    }

    public function whereIn()
    {
    }

    public function whereNotIn()
    {
    }

    public function whereBetween()
    {
    }

    public function whereNotBetween()
    {
    }

    public function orWhere()
    {
    }

    public function orWhereIn()
    {
    }

    public function orWhereNotIn()
    {
    }

    public function orWhereBetween()
    {
    }

    public function orWhereNotBetween()
    {
    }

    public function select()
    {
        //@todo
    }


    public function orderBy(string $field, string $direction = 'asc'): void
    {
        $this->search['sort'][] = [
            $field => [
                'order' => $direction
            ]
        ];
    }

    public function take(int $limit): void
    {
        $this->search['size'] = $limit;
    }

    public function offset(int $value): void
    {
        $this->search['from'] = [
            "from" => $value
        ];
    }

    public function refreshSearch(): static
    {
        $this->search = [];

        return $this;
    }

    public function limit(int $limit): void
    {
        $this->search['size'] = $limit;
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function destroy(array $ids): true
    {
        $this->refreshSearch()
            ->search['query']['bool']['must'][] = [
            'ids' => [
                'values' => $ids
            ]
        ];

        $result = Elasticsearch::setModel(static::class)
            ->post("_doc/_delete_by_query", $this->search);

        $result->throw();

        return true;
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     * @throws FieldNotDefinedInIndexException
     */
    public function checkMapping(array $options): void
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
        $this->checkMapping($this->attributes);

        Elasticsearch::setModel(static::class)->post(data: $this->attributes);

        return $this;
    }
}