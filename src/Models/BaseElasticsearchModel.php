<?php

namespace Mawebcoder\Elasticsearch\Models;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use PHPUnit\Runner\FileDoesNotExistException;
use ReflectionException;

abstract class BaseElasticsearchModel
{

    public array $attributes = [];

    public array $search = [
        "sort" => [],
        "query" => [

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

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function first(): null|static
    {
        $this->search['size'] = 1;

        $result = $this->requestForSearch();

        $resultCount = $result['hits']['total']['value'];

        if (!$resultCount) {
            return null;
        }

        $result = $result['hits']['hits'][0]['_source'];

        return $this->mapResultToModelObject($result);
    }

    public function mapResultToCollection(array $result): Collection
    {
        $total = $result['hits']['total']['value'];

        if (!$total) {
            return collect();
        }

        $results = $result['hits']['hits'];

        $collection = collect();

        foreach ($results as $value) {
            $collection->add($this->mapResultToModelObject($value['_source']));
        }

        return $collection;
    }

    public function mapResultToModelObject(array $result): null|static
    {
        $object = new static();

        foreach ($result as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }

    /**
     * @return array|mixed
     * @throws ReflectionException
     * @throws RequestException
     */
    public function requestForSearch(): mixed
    {
        $response = Elasticsearch::setModel(static::class)
            ->post('_doc/_search/_source', $this->search);

        $response->throw();

        return $response->json();
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function get(): Collection
    {
        $response = $this->requestForSearch();

        return $this->mapResultToCollection($response);
    }


    public function where(string $field, ?string $operation = null, ?string $value = null): void
    {
        if (!$value) {
            $value = $operation;
            $operation = '=';
        }
        if (!$operation) {
            $operation = '=';
        }

        switch ($operation) {
            case "<>":
            case "!=":
                $this->search['query']['bool']['must_not'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;

            case ">":
                $this->search['query']['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;
            case ">=":
                $this->search['query']['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;
            case "<":
                $this->search['query']['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];
                break;
            case "<=":
                $this->search['query']['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];
                break;
            case '=':
            default:
                $this->search['query']['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;
        }
    }

    public function whereIn(string $field, array $values): void
    {
        $this->search['query']['bool']['must'][] = [
            'terms' => [
                $field => $values
            ]
        ];
    }

    public function whereNotIn(string $field, array $values): void
    {
        $this->search['query']['bool']['must_not'][] = [
            'terms' => [
                $field => $values
            ]
        ];
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function whereBetween(string $field, array $values): void
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        $this->search['query']['bool']['must'][] = [
            'range' => [
                $field => [
                    'gte' => $values[0],
                    'lte' => $values[1]
                ]
            ]
        ];
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     */
    public function whereNotBetween(string $field, array $values): void
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        $this->search['query']['bool']['must'][] = [
            'range' => [
                $field => [
                    'lt' => $values[0],
                    'gt' => $values[1]
                ]
            ]
        ];
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