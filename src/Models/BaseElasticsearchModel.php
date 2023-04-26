<?php

namespace Mawebcoder\Elasticsearch\Models;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use ReflectionException;
use Throwable;

abstract class BaseElasticsearchModel
{
    public array $attributes = [];

    const SOURCE_KEY = '_source';

    const FIELD_ID = 'id';
    public const MUST_INDEX = 0;
    public const MUST_NOT_INDEX = 1;
    public array $search = [
        "query" => [
            "bool" => [
                "should" => [
                    self::MUST_INDEX => [
                        "bool" => [
                            "must" => [

                            ]
                        ]
                    ]
                ]
            ]
        ],
        self::SOURCE_KEY => [],
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
        $object = new static();

        $this->checkMapping(Arr::except($options, 'id'));

        $fields = $this->getFields();

        $notValuedFields = array_values(array_diff($fields, array_keys($options)));

        /**
         * ignore id
         */
        if (in_array('id', $notValuedFields)) {
            unset($notValuedFields[array_search('id', $notValuedFields)]);
        }

        if (array_key_exists('id', $options)) {
            $object->id = $options['id'];
        }

        foreach ($options as $key => $value) {
            $object->{$key} = $value;
        }

        foreach ($notValuedFields as $notValuedField) {
            $object->{$notValuedField} = null;
        }


        $object->save();

        return $object;
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws FieldNotDefinedInIndexException
     */
    public function update(array $options): bool
    {
        $this->checkMapping($options);

        $this->search['script']['source'] = '';

        foreach ($options as $key => $value) {
            $this->search['script']['source'] .= "ctx._source.$key=params." . $key . ';';

            $this->search['script']['params'][$key] = $value;
        }

        $this->search['script']['source'] = trim($this->search['script']['source'], ';');

        $response = Elasticsearch::setModel(static::class)
            ->post('_update_by_query', $this->search);

        $response->throw();

        $this->refreshQueryBuilder();

        return true;
    }

    private function refreshQueryBuilder(): void
    {
        $this->search = [];
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws Throwable
     */
    public function delete(): void
    {
        try {
            DB::beginTransaction();

            if ($this->mustDeleteJustSpecificRecord()) {
                $this->refreshQueryBuilder();

                $this->search['query']['bool']['should'][] = [
                    'ids' => [
                        'values' => [$this->id]
                    ]
                ];
            } elseif ($this->mustDeleteIndexWhileCallingDeleteMethod()) {
                DB::table('elastic_search_migrations_logs')
                    ->where('index', $this->getIndex())
                    ->delete();

                Elasticsearch::setModel(static::class)
                    ->delete();
                DB::commit();
                return;
            }

            $response = Elasticsearch::setModel(static::class)
                ->post('_delete_by_query', $this->search);

            $response->throw();


            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw  $exception;
        }
    }

    private function mustDeleteJustSpecificRecord(): bool
    {
        return boolval(count($this->attributes));
    }

    private function mustDeleteIndexWhileCallingDeleteMethod(): bool
    {
        if (!isset($this->search['query'])) {
            return true;
        }

        if (!isset($this->search['query']['bool'])) {
            return true;
        }

        if (!isset($this->search['query']['bool']['should'])) {
            return true;
        }

        if (!empty($this->search['query']['bool']['should'])) {
            return true;
        }

        return false;
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function find($id): ?static
    {
        $this->search['query']['bool']['should'] = [];

        $this->search['query']['bool']['should'][] = [
            'ids' => [
                'values' => [$id]
            ]
        ];

        $response = Elasticsearch::setModel(static::class)
            ->post('_search', $this->search);

        $response->throw();

        $result = $response->json();


        $result = $result['hits']['hits'];


        if (!count($result)) {
            return null;
        }

        $object = new static();

        $object->{self::FIELD_ID} = $result[0]['_id'];

        $result = $result[0]['_source'];

        foreach ($result as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }


    public function getAttributes(): array
    {
        return $this->attributes;
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


        $result = $result['hits']['hits'][0][static::SOURCE_KEY];


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

        foreach ($results as $result) {
            $data = [
                ...$result[self::SOURCE_KEY],
                ...['id' => $result['_id']]
            ];

            $collection->add($this->mapResultToModelObject($data));
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
            ->post('_doc/_search', $this->search);

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


    public function where(string $field, ?string $operation = null, ?string $value = null): static
    {
        [$value, $operation] = $this->getOperationValue($value, $operation);


        switch ($operation) {
            case "<>":
            case "!=":
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;

            case ">":

                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;
            case ">=":
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;
            case "<":

                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];
                break;
            case "<=":
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];
                break;

            case "like":

                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

                break;

            case "not like":
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ],
                    ]
                ];
                break;
            case '=':
            default:
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;
        }

        return $this;
    }

    public function whereTerm(string $field, ?string $operation = null, ?string $value = null): static
    {
        list($value, $operation) = $this->getOperationValue($value, $operation);

        switch ($operation) {
            case "<>":
            case "!=":
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
            case '=':
            default:
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
        }

        return $this;
    }

    public function orWhereTerm(string $field, ?string $operation = null, ?string $value = null): static
    {
        list($value, $operation) = $this->getOperationValue($value, $operation);

        switch ($operation) {
            case "<>":
            case "!=":
                $this->search['query']['bool']['should'][]['bool']['must_not'] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
            case '=':
            default:
                $this->search['query']['bool']['should'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
        }

        return $this;
    }

    public function whereIn(string $field, array $values): static
    {
        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        return $this;
    }


    public function whereNotIn(string $field, array $values): static
    {
        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        return $this;
    }

    /**
     * @param int[] $values
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function whereBetween(string $field, array $values): static
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $field => [
                    'gte' => $values[0],
                    'lte' => $values[1]
                ]
            ]
        ];

        return $this;
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function whereNotBetween(string $field, array $values): static
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
            'range' => [
                $field => [
                    'gt' => $values[1],
                ]
            ]
        ];

        $this->search['query']['bool']['should'][]['bool']['must'][] = [
            'range' => [
                $field => [
                    'lt' => $values[0],
                ]
            ]
        ];


        return $this;
    }

    public function orWhere(string $field, ?string $operation = null, ?string $value = null): static
    {
        list($value, $operation) = $this->getOperationValue($value, $operation);

        switch ($operation) {
            case "<>":
            case "!=":
                $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;

            case ">":
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;
            case ">=":
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;
            case "<":
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];
                break;
            case "<=":
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];
                break;
            case "like":
                $this->search['query']['bool']['should'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

                break;

            case "not like":
                $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;

            case '=':
            default:
                $this->search['query']['bool']['should'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;
        }
        return $this;
    }

    public function orWhereIn(string $field, array $values): static
    {
        $this->search['query']['bool']['should'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        return $this;
    }

    public function orWhereNotIn(string $field, array $values): static
    {
        $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        return $this;
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function orWhereBetween(string $field, array $values): static
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        $this->search['query']['bool']['should'][] = [
            'range' => [
                $field => [
                    'gte' => $values[0],
                    'lte' => $values[1]
                ]
            ]
        ];

        return $this;
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function orWhereNotBetween(string $field, array $values): static
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
            'range' => [
                $field => [
                    'lt' => $values[0],
                    'gt' => $values[1]
                ]
            ]
        ];

        return $this;
    }

    /**
     * @return $this
     * @throws AtLeastOneArgumentMustBeChooseInSelect
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function select(): static
    {
        if (empty(func_get_args())) {
            throw  new AtLeastOneArgumentMustBeChooseInSelect();
        }

        $this->validateIncomeSelection(func_get_args());

        $fields = [];

        foreach ($this->search['_source'] as $field) {
            $fields[] = $field;
        }

        foreach (func_get_args() as $field) {
            $fields[] = $field;
        }

        $fields = array_unique($fields);

        $this->search['_source'] = $fields;

        return $this;
    }

    /**
     * @throws SelectInputsCanNotBeArrayOrObjectException
     */
    public function validateIncomeSelection(array $incomeSelections): void
    {
        foreach ($incomeSelections as $value) {
            if (is_array($value) || is_object($value)) {
                throw new SelectInputsCanNotBeArrayOrObjectException(
                    message: 'select inputs can not be array or objects.just scalar types are valid'
                );
            }
        }
    }


    /**
     * @throws InvalidSortDirection
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        if ($direction && !in_array($direction, ['asc', 'desc'])) {
            throw new InvalidSortDirection(message: 'sort direction must be either asc or desc.');
        }

        $this->search['sort'][] = [
            $field => [
                'order' => $direction
            ]
        ];

        return $this;
    }

    public function take(int $limit): static
    {
        $this->search['size'] = $limit;
        return $this;
    }

    public function offset(int $value): static
    {
        $this->search['from'] = $value;

        return $this;
    }

    public function refreshSearch(): static
    {
        $this->search = [];

        return $this;
    }

    public function limit(int $limit): static
    {
        return $this->take($limit);
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function destroy(array $ids): bool
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
        $fields = $this->getFields();

        foreach ($options as $field => $option) {
            if (!in_array($field, $fields)) {
                throw new FieldNotDefinedInIndexException(
                    message: "field with name " . $field . " not defined in model index"
                );
            }
        }
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function getFields(): array
    {
        return Elasticsearch::setModel(static::class)
            ->getFields();
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function save(): static
    {
        Elasticsearch::setModel(static::class)->post(
            path: array_key_exists('id', $this->attributes) ?
                "_doc/" . $this->attributes['id'] : '_doc',
            data: Arr::except($this->attributes, 'id')
        );

        return $this;
    }

    /**
     * @param string|null $value
     * @param string|null $operation
     * @return null[]|string[]
     */
    public function getOperationValue(?string $value, ?string $operation): array
    {
        if (!$value) {
            $value = $operation;
            $operation = '=';
        }
        if (!$operation) {
            $operation = '=';
        }
        return array($value, $operation);
    }

    private function isNumericArray(array $values): bool
    {
        return is_numeric(implode('', $values));
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function getMappings(): array
    {
        return Elasticsearch::setModel(static::class)
            ->getMappings();
    }
}