<?php

namespace Mawebcoder\Elasticsearch\Models;

use Closure;
use Throwable;
use ReflectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;
use Mawebcoder\Elasticsearch\Trait\Aggregatable;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Trait\HasElasticsearchResponseParser;
use Mawebcoder\Elasticsearch\Exceptions\FieldNotDefinedInIndexException;
use Mawebcoder\Elasticsearch\Exceptions\AtLeastOneArgumentMustBeChooseInSelect;
use Mawebcoder\Elasticsearch\Exceptions\SelectInputsCanNotBeArrayOrObjectException;
use Mawebcoder\Elasticsearch\Exceptions\WrongArgumentNumberForWhereBetweenException;

abstract class BaseElasticsearchModel
{
    use Aggregatable;
    use HasElasticsearchResponseParser;

    public array $attributes = [];

    private int $closureCounter = 0;

    private bool $mustBeSync = false;

    private string $conditionStatus;

    private array $closureConditions = [];

    /**
     * @deprecated please use KEY_ID instead
     */
    const FIELD_ID = 'id';

    const SOURCE_KEY = '_source';

    const KEY_ID = 'id';
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
        if (!isset($this->attributes[$name])) {
            return null;
        }

        return $this->attributes[$name];
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function truncate(): void
    {
        $this->search = [
            'query' => [
                'match_all' => [
                    "boost" => 2.0,
                    "_name" => "match_all_query"
                ]
            ]
        ];

        Elasticsearch::setModel(static::class)
            ->post('_delete_by_query', $this->search, $this->mustBeSync);
    }

    public function isExistsIndex()
    {
        try {
            $response = Elasticsearch::setModel(static::class)->head();
            return $response->getStatusCode() == Response::HTTP_OK;
        } catch (ClientException $throwable) {
            return false;
        }
    }

    /**
     * @return $this
     * @throws FieldNotDefinedInIndexException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function save(): static
    {
        $hasCustomId = $this->hasCustomId();

        $path = $hasCustomId
            ? sprintf("_doc/%s", $this->attributes['id'])
            : '_doc';

        $data = Arr::except($this->attributes, 'id');

        $response = Elasticsearch::setModel(static::class)
            ->post(path: $path, data: $data, mustBeSync: $this->mustBeSync);

        $object = new static();

        $attributes = $this->getAttributes();

        // get the mappings from model and current field to set null fields that doesn't have any value
        $nullValueFields = $this->getNullValueFields($attributes);

        // Todo:needs more explanation
        if (in_array('id', $nullValueFields)) {
            unset($nullValueFields[array_search('id', $nullValueFields)]);
        }

        // sure if it doesn't have id and get if from elasticsearch
        // update the search query and also set the id that get from elasticsearch
        if (!$hasCustomId) {
            $object->id = $this->getIdFromElasticsearchResponse($response);
            $this->updateQueryWithInsinuatedObject($object->id, $object);
        }

        foreach ($attributes as $key => $value) {
            $object->{$key} = $value;
        }

        foreach ($nullValueFields as $notValuedField) {
            $object->{$notValuedField} = null;
        }

        return $object;
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function saveMany(array $items)
    {
        $items = Arr::wrap($items);

        // elasticsearch for doing bulk write need NDJSON data type instead of JSON
        $bodyPayload = $this->generateNdJsonForBulkWrite($items);

        return Elasticsearch::setModel(static::class)
            ->post('_bulk', $bodyPayload, $this->mustBeSync);
    }

    /**
     * @param array $options
     * @return bool
     * @throws FieldNotDefinedInIndexException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function update(array $options): bool
    {
        // Todo: disable check mapping because has bug on the object type
        // $this->checkMapping($options);

        $this->search['script']['source'] = '';

        foreach ($options as $key => $value) {
            $parameterName = str_replace('.', '_', $key);
            $this->search['script']['source'] .= "ctx._source.$key=params." . $parameterName . ';';
            $this->search['script']['params'][$parameterName] = $value;
        }

        $this->search['script']['source'] = trim($this->search['script']['source'], ';');

        Elasticsearch::setModel(static::class)
            ->post('_update_by_query', $this->search, $this->mustBeSync);

        $this->refreshQueryBuilder();

        return true;
    }

    public function mustBeSync(): static
    {
        $this->mustBeSync = true;
        return $this;
    }

    public function mustBeAsync(): static
    {
        $this->mustBeSync = false;
        return $this;
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
            }

            Elasticsearch::setModel(static::class)
                ->post('_delete_by_query', $this->search, $this->mustBeSync);

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

    /**
     * @param $id
     * @return $this|null
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function find($id): ?static
    {
        $this->updateQueryWithInsinuatedObject($id);

        $response = Elasticsearch::setModel(static::class)
            ->post('_search', $this->search);

        $result = json_decode($response->getBody(), true);

        $result = $result['hits']['hits'];

        if (!count($result)) {
            return null;
        }

        $result = $result[0];
        $sourceResult = $result[self::SOURCE_KEY];

        $object = new static();
        $object->search = $this->search;

        $object->{self::KEY_ID} = is_numeric($result['_id']) ? intval($result['_id']) : $result['_id'];

        foreach ($sourceResult as $key => $value) {
            $object->{$key} = $value;
        }

        foreach ($this->getNullValueFields($object->getAttributes()) as $nullKey) {
            $object->{$nullKey} = null;
        }

        return $object;
    }


    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return $this|null
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function first(): null|static
    {
        $this->search['size'] = 1;

        $this->organizeClosuresCalls();

        $result = $this->requestForSearch();

        $resultCount = $result['hits']['total']['value'];

        if (!$resultCount) {
            return null;
        }

        $result = $result['hits']['hits'][0][static::SOURCE_KEY];

        return $this->mapResultToModelObject($result);
    }

    private function organizeClosuresCalls(): void
    {
        $this->handleOrWhereClosure();

        $this->handleWhereClosure();
    }

    public function mapResultToCollection(array $result): Collection
    {
        $aggregations = $result['aggregations'] ?? null;

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

        if ($aggregations) {
            return collect([
                'data' => $collection,
                'aggregations' => $aggregations
            ]);
        }

        return $collection;
    }


    public function mapResultToModelObject($result): static
    {
        $object = new static();

        foreach ($result as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }


    /**
     * @return mixed
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function requestForSearch(): mixed
    {
        $response = Elasticsearch::setModel(static::class)
            ->post('_doc/_search', $this->search);

        return json_decode($response->getBody(), true);
    }

    /**
     * @return Collection
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function get(): Collection
    {
        $this->organizeClosuresCalls();

        $response = $this->requestForSearch();

        return $this->mapResultToCollection($response);
    }

    /**
     * @throws Throwable
     */
    public function where(
        $field,
        $operation = null,
        $value = null,
        bool $inClosure = false
    ): static|array {
        $numberOfArguments = count(func_get_args());

        [$value, $operation] = $this->getOperationValue($value, $operation, $numberOfArguments);

        if ($inClosure) {
            return match ($operation) {
                "<>", "!=" => [
                    'bool' => [
                        'must_not' => [
                            [
                                "term" => [
                                    $field => [
                                        'value' => $value
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                ">" => [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ],
                ">=" => [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ],
                "<" => [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ],
                "<=" => [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ],
                "like" => [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ],
                "not like" => [
                    'bool' => [
                        'must_not' => [
                            [
                                "match_phrase_prefix" => [
                                    $field => [
                                        'query' => $value
                                    ],
                                ]
                            ]
                        ]
                    ]
                ],
                default => [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ],
            };
        }

        if ($field instanceof Closure) {
            $this->closureCounter++;
            $this->conditionStatus = 'where';

            $field($this);

            return $this;
        }

        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';

        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => $operation,
                'condition' => 'and',
                'method' => 'where'
            ];

            return $this;
        }


        switch ($operation) {
            case "<>":
            case "!=":
                $this->search['query']['bool']['should']
                [self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
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

    private function isCalledFromClosure(string $parentFunction): bool
    {
        return $parentFunction == '{closure}';
    }

    public function whereTerm(
        string $field,
        $operation = null,
        $value = null,
        bool $inClosure = false
    ): static|array {
        $numberOfArguments = count(func_get_args());

        list($value, $operation) = $this->getOperationValue($value, $operation, $numberOfArguments);

        if ($inClosure) {
            return match ($operation) {
                "<>", "!=" => [
                    'bool' => [
                        'must_not' => [
                            [
                                "match" => [
                                    $field => [
                                        'query' => $value
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                default => [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ],
            };
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => $operation,
                'condition' => 'and',
                'method' => 'whereTerm'
            ];
            return $this;
        }

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

    public function orWhereTerm(
        $field,
        $operation = null,
        $value = null,
        bool $inClosure = false
    ): static|array {
        $numberOfArguments = count(func_get_args());

        list($value, $operation) = $this->getOperationValue($value, $operation, $numberOfArguments);

        if ($inClosure) {
            return match ($operation) {
                "<>", "!=" => [
                    'bool' => [
                        'must_not' => [
                            [
                                "match" => [
                                    $field => [
                                        'query' => $value
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                default => [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ],
            };
        }

        if ($field instanceof Closure) {
            $this->closureCounter++;
            $field($this);
            return $this;
        }

        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => $operation,
                'condition' => 'or',
                'method' => 'orWhereTerm'
            ];
            return $this;
        }

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

    public function whereIn(string $field, array $values, bool $inClosure = false): static|array
    {
        if ($inClosure) {
            return [
                'terms' => [
                    $field => $values
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'and',
                'method' => 'whereIn'
            ];
            return $this;
        }

        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        return $this;
    }


    public function whereNotIn(string $field, array $values, bool $inClosure = false): static|array
    {
        if ($inClosure) {
            return [
                'bool' => [
                    'must_not' => [
                        [
                            'terms' => [
                                $field => $values
                            ]
                        ]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'and',
                'method' => 'whereNotIn'
            ];
            return $this;
        }
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
    public function whereBetween(string $field, array $values, bool $inClosure = false): static|array
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        if ($inClosure) {
            return [
                'range' => [
                    $field => [
                        'gte' => $values[0],
                        'lte' => $values[1]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'and',
                'method' => 'whereBetween'
            ];
            return $this;
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

    public function dd(): void
    {
        $this->organizeClosuresCalls();

        dd($this->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function whereNotBetween(string $field, array $values, bool $inClosure = false): static|array
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        if ($inClosure) {
            return [
                'bool' => [
                    'must_not' => [
                        'range' => [
                            $field => [
                                'gt' => $values[1],
                                'lt' => $values[0]
                            ]
                        ]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'and',
                'method' => 'whereNotBetween'
            ];
            return $this;
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

    public function orWhere(
        $field,
        $operation = null,
        $value = null,
        bool $inClosure = false
    ): static|array {
        $numberOfArguments = count(func_get_args());

        list($value, $operation) = $this->getOperationValue($value, $operation, $numberOfArguments);

        if ($inClosure) {
            return match ($operation) {
                "<>", "!=" => [
                    'bool' => [
                        'must_not' => [
                            [
                                "term" => [
                                    $field => [
                                        'value' => $value
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                ">" => [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ],
                ">=" => [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ],
                "<" => [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ],
                "<=" => [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ],
                "like" => [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ],
                "not like" => [
                    'bool' => [
                        'must_not' => [
                            [
                                "match_phrase_prefix" => [
                                    $field => [
                                        'query' => $value
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                default => [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ],
            };
        }
        if ($field instanceof Closure) {
            $this->closureCounter++;
            $this->conditionStatus = 'orWhere';
            $field($this);

            return $this;
        }


        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';


        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => $operation,
                'condition' => 'or',
                'method' => 'orWhere'
            ];
            return $this;
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions['orWhere'][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => $operation,
                'condition' => 'or'
            ];

            return $this;
        }

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

    public function orWhereIn(string $field, array $values, bool $inClosure = false): static|array
    {
        if ($inClosure) {
            return [
                'terms' => [
                    $field => $values
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'or',
                'method' => 'orWhereIn'
            ];
            return $this;
        }
        $this->search['query']['bool']['should'][] = [
            'terms' => [
                $field => $values
            ]
        ];

        return $this;
    }

    public function orWhereNotIn(string $field, array $values, bool $inClosure = false): static|array
    {
        if ($inClosure) {
            return [
                'bool' => [
                    'must_not' => [
                        [
                            'terms' => [
                                $field => $values
                            ]
                        ]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'or',
                'method' => 'orWhereNotIn'
            ];
            return $this;
        }
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
    public function orWhereBetween(string $field, array $values, bool $inClosure = false): static|array
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        if ($inClosure) {
            return [
                'range' => [
                    $field => [
                        'gte' => $values[0],
                        'lte' => $values[1]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'or',
                'method' => 'orWhereBetween'
            ];
            return $this;
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
    public function orWhereNotBetween(string $field, array $values, bool $inClosure = false): static|array
    {
        if (count($values) != 2) {
            throw new WrongArgumentNumberForWhereBetweenException(message: 'values members must be 2');
        }

        if (!$this->isNumericArray($values)) {
            throw new WrongArgumentType(message: 'values must be numeric.');
        }

        if ($inClosure) {
            return [
                'bool' => [
                    'must_not' => [
                        [
                            'range' => [
                                $field => [
                                    'lt' => $values[0],
                                    'gt' => $values[1]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $values,
                'operation' => null,
                'condition' => 'or',
                'method' => 'orWhereNotBetween'
            ];
            return $this;
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

        foreach ($this->search[self::SOURCE_KEY] as $field) {
            $fields[] = $field;
        }

        foreach (func_get_args() as $field) {
            $fields[] = $field;
        }

        $fields = array_unique($fields);

        $this->search[self::SOURCE_KEY] = $fields;

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
     * @param array $ids
     * @return bool
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function destroy(array $ids): bool
    {
        $this->refreshSearch()
            ->search['query']['bool']['must'][] = [
            'ids' => [
                'values' => $ids
            ]
        ];

        Elasticsearch::setModel(static::class)
            ->post("_doc/_delete_by_query", $this->search);

        return true;
    }


    /**
     * @param array $options
     * @return void
     * @throws FieldNotDefinedInIndexException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
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
     * @return array
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function getFields(): array
    {
        return Elasticsearch::setModel(static::class)
            ->getFields();
    }


    public function getOperationValue($value, $operation, int $numberOfArguments): array
    {
        if ($numberOfArguments === 2) {
            $value = $operation;
            $operation = '=';
            return array($value, $operation);
        }

        return array($value, $operation);
    }

    private function isNumericArray(array $values): bool
    {
        return is_numeric(implode('', $values));
    }

    /**
     * @return array
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function getMappings(): array
    {
        return Elasticsearch::setModel(static::class)
            ->getMappings();
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function paginate(int $perPage = 15): Collection
    {
        $totalRecords = $this->count();

        $currentPage = request('page') ?? 1;

        $firstPage = 1;

        $nextLink = null;

        $previousLink = null;

        $lastPage = ceil($totalRecords / $perPage);

        if ($currentPage > $lastPage || $currentPage < $firstPage) {
            return collect([
                'total_records' => $totalRecords,
                'last_page' => $lastPage,
                'current_page' => $currentPage,
                'next_link' => $nextLink,
                'prev_link' => $previousLink,
                'data' => collect([]),
            ]);
        }

        if ($lastPage !== $currentPage && $currentPage !== $firstPage) {
            $nextLink = request()->fullUrl() . "?" . http_build_query(['page' => $currentPage + 1]);
        }

        if ($currentPage !== $firstPage) {
            $previousLink = request()->fullUrl() . "?" . http_build_query(['page' => $currentPage - 1]);
        }

        $result = $this
            ->limit($perPage)
            ->offset($perPage * ($currentPage - 1))
            ->get();

        return collect([
            'total_records' => $totalRecords,
            'last_page' => $lastPage,
            'current_page' => $currentPage,
            'next_link' => $nextLink,
            'prev_link' => $previousLink,
            'data' => $result,
        ]);
    }

    public function whereFuzzy(
        string $field,
        string $value,
        string|int $fuzziness = 3,
        int $prefixLength = 0,
        bool $inClosure = false
    ): static|array {
        if ($inClosure) {
            return [
                [
                    'fuzzy' => [
                        $field => [
                            "value" => $value,
                            "fuzziness" => $fuzziness,
                            'prefix_length' => $prefixLength
                        ]
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => null,
                'condition' => 'and',
                'method' => 'whereFuzzy'
            ];
            return $this;
        }
        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
            'fuzzy' => [
                $field => [
                    "value" => $value,
                    "fuzziness" => $fuzziness,
                    'prefix_length' => $prefixLength
                ]
            ]
        ];

        return $this;
    }

    public function orWhereFuzzy(
        string $field,
        string $value,
        string|int $fuzziness = 3,
        int $prefixLength = 0,
        bool $isClosure = false
    ): static|array {
        if ($isClosure) {
            return [
                'fuzzy' => [
                    $field => [
                        "value" => $value,
                        "fuzziness" => $fuzziness,
                        'prefix_length' => $prefixLength
                    ]
                ]
            ];
        }
        $backtrace = debug_backtrace();

        $parentFunction = isset($backtrace[1]) ? $backtrace[1]['function'] : '';
        if ($this->isCalledFromClosure($parentFunction)) {
            $this->closureConditions[$this->conditionStatus][$this->closureCounter][] = [
                'field' => $field,
                'value' => $value,
                'operation' => null,
                'condition' => 'or',
                'method' => 'whereFuzzy'
            ];
            return $this;
        }

        $this->search['query']['bool']['should'][] = [
            'fuzzy' => [
                $field => [
                    "value" => $value,
                    "fuzziness" => $fuzziness,
                    'prefix_length' => $prefixLength
                ]
            ]
        ];

        return $this;
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function findMany(array $ids): Collection
    {
        $this->refreshSearch();

        $this->search['query']["ids"] = [
            'values' => $ids
        ];

        return $this->get();
    }

    public static function newQuery(): static
    {
        return new static();
    }


    private function isOrConditionsInClosure(array $conditions): bool
    {
        $isOr = false;

        foreach ($conditions as $condition) {
            if ($condition['condition'] == 'or') {
                $isOr = true;
            }
        }
        return $isOr;
    }

    private function getLastActiveKey(false|int|string $lastKey): bool|int|string
    {
        if ($lastKey === 0 || $lastKey) {
            $lastKey++;
        } else {
            $lastKey = 0;
        }
        return $lastKey;
    }

    public function handleOrWhereClosure(): void
    {
        if (!isset($this->closureConditions['orWhere'])) {
            return;
        }


        foreach ($this->closureConditions['orWhere'] as $conditions) {
            $lastKey = array_search(
                Arr::last($this->search['query']['bool']['should']),
                $this->search['query']['bool']['should']
            );

            $currentIndex = $this->getLastActiveKey($lastKey);
            /**
             * if there is "or" condition between conditional methods that has been called inside closure function
             */

            if ($this->isOrConditionsInClosure($conditions)) {
                foreach ($conditions as $condition) {
                    $method = $condition['method'];
                    if (is_null($condition['operation'])) {
                        $this->search['query']['bool']['should'][$currentIndex]['bool']['should'][] = $this->{$method}(
                            $condition['field'],
                            $condition['value'],
                            inClosure: true
                        );
                    } else {
                        $this->search['query']['bool']['should'][$currentIndex]['bool']['should'][] = $this->{$method}(
                            $condition['field'],
                            $condition['operation'],
                            $condition['value'],
                            inClosure: true
                        );
                    }
                }
            } else {
                /**
                 * if there is "and" condition between conditional methods that has been called inside closure function
                 */
                foreach ($conditions as $condition) {
                    $method = $condition['method'];
                    if (is_null($condition['operation'])) {
                        $this->search['query']['bool']['should'][$currentIndex]['bool']['must'][] = $this->{$method}(
                            $condition['field'],
                            $condition['value'],
                            inClosure: true
                        );
                    } else {
                        $this->search['query']['bool']['should'][$currentIndex]['bool']['must'][] = $this->{$method}(
                            $condition['field'],
                            $condition['operation'],
                            $condition['value'],
                            inClosure: true
                        );
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    public function handleWhereClosure(): void
    {
        if (!isset($this->closureConditions['where'])) {
            return;
        }


        if (isset($this->closureConditions['where'])) {
            foreach ($this->closureConditions['where'] as $conditions) {
                $lastKey = array_search(
                    Arr::last($this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must']),
                    $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must']
                );

                $currentIndex = $this->getLastActiveKey($lastKey);
                /**
                 * if there is "or" condition between conditional methods that has been called inside closure function
                 */
                if ($this->isOrConditionsInClosure($conditions)) {
                    foreach ($conditions as $condition) {
                        $method = $condition['method'];
                        if (is_null($condition['operation'])) {
                            $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][$currentIndex]['bool']['should'][] = $this->{$method}(
                                $condition['field'],
                                $condition['value'],
                                inClosure: true
                            );
                        } else {
                            $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][$currentIndex]['bool']['should'][] = $this->{$method}(
                                $condition['field'],
                                $condition['operation'],
                                $condition['value'],
                                inClosure: true
                            );
                        }
                    }
                } else {
                    /**
                     * if there is "and" condition between conditional methods that has been called inside closure function
                     */
                    foreach ($conditions as $condition) {
                        $method = $condition['method'];
                        if (is_null($condition['operation'])) {
                            $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][$currentIndex]['bool']['must'][] = $this->{$method}(
                                $condition['field'],
                                $condition['value'],
                                inClosure: true
                            );
                        } else {
                            $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][$currentIndex]['bool']['must'][] = $this->{$method}(
                                $condition['field'],
                                $condition['operation'],
                                $condition['value'],
                                inClosure: true
                            );
                        }
                    }
                }
            }
        }
    }


    private function updateQueryWithInsinuatedObject($id, ?object $object = null): void
    {
        $targetObject = $object ?? $this;

        $targetObject->search['query']['bool']['should'] = [];
        $targetObject->search['query']['bool']['should'][] = [
            'ids' => [
                'values' => [$id]
            ]
        ];
    }

    /**
     * @param mixed $item
     * @return array
     */
    private function getNormalizeItemWithIdIfExists(mixed $item): array
    {
        $normalizeItem = [];
        $itemId = null;

        foreach ($item as $key => $value) {
            // set the elastic id
            if ($key == 'id') {
                $itemId = $value;
                continue;
            }

            $normalizeItem[$key] = $value;
        }

        return [$normalizeItem, $itemId];
    }

    /**
     * @param array $items
     * @return string
     * @throws \Exception
     */
    private function generateNdJsonForBulkWrite(array $items): string
    {
        $parameters = [];

        $documentInfoTemplate = [
            'index' => [
                '_index' => Elasticsearch::setModel(static::class)->getIndexNameWithPrefix(),
            ]
        ];

        foreach ($items as $item) {
            [$normalizeItem, $id] = $this->getNormalizeItemWithIdIfExists($item);

            $documentInfo = $documentInfoTemplate;

            if (!is_null($id)) {
                $documentInfo['index']['_id'] = $id;
            }

            $parameters[] = json_encode($documentInfo);
            $parameters[] = json_encode($normalizeItem);
        }

        return implode("\n", $parameters) . "\n";
    }

    /**
     * @return bool
     */
    private function hasCustomId(): bool
    {
        return array_key_exists('id', $this->attributes);
    }

    /**
     * @param array $currentAttributes
     * @return array
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     */
    private function getNullValueFields(array $currentAttributes): array
    {
        $fields = $this->getFields();

        return array_values(
            array_diff($fields, array_keys($currentAttributes))
        );
    }
}
