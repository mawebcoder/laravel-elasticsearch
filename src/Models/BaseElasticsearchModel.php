<?php

namespace Mawebcoder\Elasticsearch\Models;

use Closure;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Mawebcoder\Elasticsearch\Enums\ConditionsEnum;
use Mawebcoder\Elasticsearch\Exceptions\DirectoryNotFoundException;
use Mawebcoder\Elasticsearch\Exceptions\IndexNamePatternIsNotValidException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use ReflectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
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

    public const FIELD_GROUP_BY_INDEX = 0;
    public const FIELD_GROUP_BY_VALUE_INDEX = 0;

    public const GROUP_BY_PREFIX = 'groupBy_';
    public array $attributes = [];
    public const OPERATOR_LIKE = 'like';
    public const OPERATOR_NOT_LIKE = 'not like';
    public const   OPERATOR_EQUAL = '=';
    public const   OPERATOR_NOT_EQUAL = '!=';
    public const   OPERATOR_NOT_EQUAL_SPACESHIP = '<>';
    public const   OPERATOR_LT = '<';
    public const   OPERATOR_GT = '>';
    public const   OPERATOR_GTE = '>=';
    public const   OPERATOR_LTE = '<=';

    private bool $mustBeSync = false;

    /**
     * @deprecated please use KEY_ID instead
     */
    public const FIELD_ID = 'id';

    public array $wheres = [];

    public const SOURCE_KEY = '_source';

    public const KEY_ID = 'id';
    public const MUST_INDEX = 0;
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

    /**
     * @throws IndexNamePatternIsNotValidException
     */
    public function getIndexWithPrefix(): string
    {
        $index = '';

        if (config('elasticsearch.index_prefix')) {
            $index = config('elasticsearch.index_prefix');
        }

        $index .= $this->getIndex();

        $this->validateIndex($index);

        return $index;
    }


    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     */
    public function truncate(): void
    {
        $this->refreshSearch();

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


    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
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


        if (in_array('id', $nullValueFields, true)) {
            unset($nullValueFields[array_search('id', $nullValueFields, true)]);
        }

        // sure if it doesn't have id and get if from elasticsearch
        // update the search query and also set the id that get from elasticsearch
        if (!$hasCustomId) {
            $object->{self::KEY_ID} = $this->getIdFromElasticsearchResponse($response);
            $this->updateQueryWithInsinuatedObject($object->{self::KEY_ID}, $object);
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
     * @throws Exception
     */
    public function saveMany(array $items): ResponseInterface
    {
        $items = Arr::wrap($items);

        $bodyPayload = $this->generateNdJsonForBulkWrite($items);

        return Elasticsearch::setModel(static::class)
            ->post('_bulk', $bodyPayload, $this->mustBeSync);
    }


    /**
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     */
    public function update(array $options): bool
    {
        if ($this->isCalledFromObject()) {

            $this->refreshSearch()
                ->search['query']['bool']['must'][] = [
                'ids' => [
                    'values' => [$this->{self::KEY_ID}]
                ]
            ];
        }
        $script = trim($this->buildScript($options), ';');

        $this->search['script']['source'] = $script;

        Elasticsearch::setModel(static::class)
            ->post('_update_by_query', $this->search, $this->mustBeSync);

        $this->refreshQueryBuilder();

        return true;
    }

    /**
     * @throws JsonException
     */
    public function buildScript($data, $path = 'ctx._source'): string
    {
        $script = '';

        foreach ($data as $key => $value) {
            $currentPath = $path . '.' . $key;

            if (is_array($value)) {
                $nestedScript = $this->buildScript($value, $currentPath);
                $script .= $nestedScript . ';';
            } else {
                $value = is_string($value) ? '"' . addslashes($value) . '"' : json_encode($value, JSON_THROW_ON_ERROR);
                $script .= "$currentPath = $value;";
            }
        }

        return $script;
    }

    public function mustBeSync(): static
    {
        $this->mustBeSync = true;
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

            if ($this->isCalledFromObject()) {
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

    private function isCalledFromObject(): bool
    {
        return (bool)count($this->attributes);
    }

    /**
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     * @throws JsonException
     * @throws IndexNamePatternIsNotValidException
     */
    public function find($id): ?static
    {
        $this->updateQueryWithInsinuatedObject($id);

        $response = Elasticsearch::setModel(static::class)
            ->post('_search', $this->search);

        $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $result = $result['hits']['hits'];

        if (!count($result)) {
            return null;
        }

        $result = $result[0];
        $sourceResult = $result[self::SOURCE_KEY];

        $object = new static();

        $object->search = $this->search;

        $object->{self::KEY_ID} = is_numeric($result['_id']) ? (int)$result['_id'] : $result['_id'];

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
     * @throws JsonException|IndexNamePatternIsNotValidException
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function mapResultToCollection(array $result): Collection
    {
        $aggregations = $result['aggregations'] ?? null;

        $total = $result['hits']['total']['value'];

        if (!$total) {
            return collect();
        }

        $results = $result['hits']['hits'];

        $collection = collect();

        if ($this->isCalledFromGroupBy()) {

            return $this->FetchGroupByData($results);
        }

        foreach ($results as $record) {
            $data = [
                ...$record[self::SOURCE_KEY],
                ...['id' => $record['_id']]
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


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function mapResultToModelObject($result): static
    {
        $object = new static();

        foreach ($result as $key => $value) {
            $object->{$key} = $value;
        }

        $mappings = $this->getMappings();

        foreach ($mappings as $key => $value) {

            if (!is_null($object->{$key})) {
                continue;
            }
            $object->{$key} = null;
        }

        return $object;
    }


    /**
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws JsonException|IndexNamePatternIsNotValidException
     */
    public function requestForSearch(): mixed
    {
        if (!isset($this->search['size'])) {
            $this->search['size'] = $this->count();
        }

        $response = Elasticsearch::setModel(static::class)
            ->post('_doc/_search', $this->search);

        return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws ReflectionException|IndexNamePatternIsNotValidException
     */
    public function get(): Collection
    {
        $this->buildQuery();


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
    ): static|array
    {
        $numberOfArguments = count(func_get_args());

        $field = $this->parseField($field);

        [$value, $operation] = $this->getOperationValue($value, $operation, $numberOfArguments);

        if (is_callable($field)) {
            $query = $field(static::newQuery());

            $this->wheres[ConditionsEnum::WHERE->value][] = $query;

            return $this;
        }
        $this->getQueryForWhere($operation, $value, $field);


        return $this;
    }

    public function orWhere(
        $field,
        $operation = null,
        $value = null,
    ): static|array
    {
        $numberOfArguments = count(func_get_args());

        $field = $this->parseField($field);

        [$value, $operation] = $this->getOperationValue($value, $operation, $numberOfArguments);

        if (is_callable($field)) {
            $query = $field(static::newQuery());

            $this->wheres[ConditionsEnum::OR_WHERE->value][] = $query;
            return $this;
        }
        $this->getOrWhereQueryBuilder($operation, $value, $field);

        return $this;
    }


    public function whereTerm(
        $field,
        $operation = null,
        $value = null,
    ): static|array
    {
        $numberOfArguments = count(func_get_args());

        $field = $this->parseField($field);

        [$value, $operation] = $this->getOperationValue($value, $operation, $numberOfArguments);

        $this->getQueryBuilderForWhereTerm($operation, $field, $value);

        return $this;
    }

    public function orWhereTerm(
        $field,
        $operation = null,
        $value = null,
    ): static|array
    {
        $numberOfArguments = count(func_get_args());

        $field = $this->parseField($field);

        [$value, $operation] = $this->getOperationValue($value, $operation, $numberOfArguments);

        $this->buildQueryForOrWhereTerm($operation, $field, $value);

        return $this;
    }

    private function excludeNullValuesFromArray(array $values): array
    {
        return array_filter($values, static fn($value) => !is_null($value));
    }

    public function whereIn($field, array $values): static|array
    {
        $field = $this->parseField($field);

        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
            'terms' => [
                $field => $this->excludeNullValuesFromArray($values)
            ]
        ];


        if ($this->isThereNulValueInValues($values)) {
            $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                'exists' => [
                    'field' => $field
                ]
            ];
        }

        return $this;
    }


    public function whereNotIn($field, array $values): static|array
    {
        $field = $this->parseField($field);

        $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
            'terms' => [
                $field => array_filter($values, static fn($value) => !is_null($value))
            ]
        ];

        if ($this->isThereNulValueInValues($values)) {
            $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                'exists' => [
                    'field' => $field
                ]
            ];
        }

        return $this;
    }

    /**
     * @param int[] $values
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function whereBetween($field, array $values): static|array
    {
        $field = $this->parseField($field);

        if (count($values) !== 2) {
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

    #[NoReturn] public function dd(): void
    {
        dd($this->search);
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function whereNotBetween($field, array $values): static|array
    {
        $field = $this->parseField($field);

        if (count($values) !== 2) {
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


    public function orWhereIn($field, array $values, bool $inClosure = false): static|array
    {
        $field = $this->parseField($field);

        if ($inClosure) {
            return [
                'terms' => [
                    $field => $values
                ]
            ];
        }

        $this->search['query']['bool']['should'][] = [
            'terms' => [
                $field => $this->excludeNullValuesFromArray($values)
            ]
        ];

        if ($this->isThereNulValueInValues($values)) {
            $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                'exists' => [
                    'field' => $field
                ]
            ];
        }

        return $this;
    }

    public function orWhereNotIn($field, array $values, bool $inClosure = false): static|array
    {
        $field = $this->parseField($field);

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

        $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
            'terms' => [
                $field => $this->excludeNullValuesFromArray($values)
            ]
        ];

        if ($this->isThereNulValueInValues($values)) {
            $this->search['query']['bool']['should'][] = [
                'exists' => [
                    'field' => $field
                ]
            ];
        }
        return $this;
    }

    /**
     * @throws WrongArgumentNumberForWhereBetweenException
     * @throws WrongArgumentType
     */
    public function orWhereBetween($field, array $values, bool $inClosure = false): static|array
    {
        $field = $this->parseField($field);

        if (count($values) !== 2) {
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
    public function orWhereNotBetween($field, array $values, bool $inClosure = false): static|array
    {
        $field = $this->parseField($field);

        if (count($values) !== 2) {
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
            $parsedField = $this->parseField($field);

            $fields[] = $parsedField;
        }

        foreach (func_get_args() as $field) {
            $field = $this->parseField($field);

            $fields[] = $field;
        }

        $fields = array_filter(array_unique($fields), static fn($value) => $value);

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
    public function orderBy($field, string $direction = 'asc'): static
    {
        $field = $this->parseField($field);

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
     * @throws IndexNamePatternIsNotValidException|ReflectionException
     */
    public function destroy(array $ids): bool
    {
        $this->refreshSearch()
            ->search['query']['bool']['must'][] = [
            'ids' => [
                'values' => $this->excludeNullValuesFromArray($ids)
            ]
        ];

        Elasticsearch::setModel(static::class)
            ->post("_doc/_delete_by_query", $this->search);

        return true;
    }

    public function arrayKeysRecursiveAsFlat($array): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $keys[] = $key;
            if (is_array($value)) {
                $keys = [
                    ...$keys,
                    ...$this->arrayKeysRecursiveAsFlat($value)
                ];
            }
        }

        return $keys;
    }

    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
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
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function getMappings(): array
    {
        return Elasticsearch::setModel(static::class)
            ->getMappings();
    }

    /**
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
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
            $nextLink = request()?->fullUrl() . "?" . http_build_query(['page' => $currentPage + 1]);
        }

        if ($currentPage !== $firstPage) {
            $previousLink = request()?->fullUrl() . "?" . http_build_query(['page' => $currentPage - 1]);
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
        string|Closure $field,
        string         $value,
        string|int     $fuzziness = 3,
        int            $prefixLength = 0,
    ): static|array
    {
        $field = $this->parseField($field);

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
        string|Closure $field,
        string         $value,
        string|int     $fuzziness = 3,
        int            $prefixLength = 0,

    ): static|array
    {
        $field = $this->parseField($field);

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

    public function whereNull(string $field): self
    {
        $this->getQueryForWhere(self::OPERATOR_EQUAL, null, $field);

        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $this->getQueryForWhere(self::OPERATOR_NOT_EQUAL, null, $field);
        return $this;
    }

    public function orWhereNull(string $field): static
    {
        $this->getOrWhereQueryBuilder(self::OPERATOR_EQUAL, null, $field);

        return $this;

    }

    public function orWhereNotNull(string $field): static
    {
        $this->getOrWhereQueryBuilder(self::OPERATOR_NOT_EQUAL, null, $field);

        return $this;
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     * @throws IndexNamePatternIsNotValidException
     */
    public function findMany(array $ids): Collection
    {
        $this->refreshSearch()
            ->search['query']["ids"] = [
            'values' => $this->excludeNullValuesFromArray($ids)
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
            if ($condition['condition'] === 'or') {
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
            if ($key === self::KEY_ID) {
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
     * @throws Exception
     */
    private function generateNdJsonForBulkWrite(array $items): string
    {
        $parameters = [];

        $documentInfoTemplate = [
            'index' => [
                '_index' => (new static())->getIndexWithPrefix(),
            ]
        ];

        foreach ($items as $item) {
            [$normalizeItem, $id] = $this->getNormalizeItemWithIdIfExists($item);

            $documentInfo = $documentInfoTemplate;

            if (!is_null($id)) {
                $documentInfo['index']['_id'] = $id;
            }

            $parameters[] = trim(json_encode($documentInfo, JSON_THROW_ON_ERROR));

            $parameters[] = trim(json_encode($normalizeItem, JSON_THROW_ON_ERROR));
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

    public function parseField($field): mixed
    {
        if ($field === self::KEY_ID) {
            return '_id';
        }

        return $field;
    }

    public function isNestedField(int|string $field): bool
    {
        return count($this->getNestedFieldsAsArray($field)) > 1;
    }

    /**
     * @param int|string $field
     * @return string[]
     */
    public function getNestedFieldsAsArray(int|string $field): array
    {
        return explode('.', $field);
    }

    /**
     * @param $key
     * @param array $fields
     * @return bool
     */
    public function checkFieldExistsInMapping($key, array $fields): bool
    {
        return array_key_exists($key, $fields);
    }

    private function getInClosureQueryForWhere(mixed $operation, mixed $value, string $field): array
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
            case self::OPERATOR_NOT_EQUAL:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must' => [
                                [
                                    "exists" => [
                                        "field" => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }
                $query = [
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
                ];

                break;
            case self::OPERATOR_GT:
                $query = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;

            case self::OPERATOR_GTE:
                $query = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;

            case  self::OPERATOR_LT:
                $query = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];

                break;

            case self::OPERATOR_LTE:
                $query = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];

                break;

            case self::OPERATOR_LIKE:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must_not' => [
                                [
                                    "exists" => [
                                        "field" => $field
                                    ]
                                ]
                            ]
                        ]
                    ];

                    break;
                }
                $query = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

                break;
            case self::OPERATOR_NOT_LIKE:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must' => [
                                [
                                    "exists" => [
                                        "field" => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
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
                ];

                break;

            default:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must_not' => [
                                [
                                    "exists" => [
                                        "field" => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
        }
        return $query;
    }


    public function getQueryForWhere(mixed $operation, mixed $value, Closure|string $field): void
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL:
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
                if (is_null($value)) {
                    $this->search['query']['bool']['should']
                    [self::MUST_INDEX]['bool']['must'][] = [
                        "exists" => [
                            'field' => $field
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should']
                [self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_GT:
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_GTE:
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LT:
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LTE:
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];
                break;

            case self::OPERATOR_LIKE:
                if (is_null($value)) {
                    $this->search['query']['bool']['should']
                    [self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                        "exists" => [
                            'field' => $field
                        ]
                    ];

                    break;
                }
                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

                break;

            case self::OPERATOR_NOT_LIKE:
                if (is_null($value)) {
                    $this->search['query']['bool']['should']
                    [self::MUST_INDEX]['bool']['must'][] = [
                        "exists" => [
                            "field" => $field,
                        ]
                    ];

                    break;
                }
                $this->search['query']['bool']['should']
                [self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ],
                    ]
                ];


                break;
            case '=':
            default:
                if (is_null($value)) {
                    $this->search['query']['bool']['should']
                    [self::MUST_INDEX]['bool']['must'][] = [
                        'bool' => [
                            'must_not' => [
                                [
                                    "exists" => [
                                        'field' => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should']
                [self::MUST_INDEX]['bool']['must'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
        }
    }


    public function getQueryBuilderForWhereTermInClosureState(
        mixed          $operation,
        mixed          $value,
        string|Closure $field
    ): array
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
            case self::OPERATOR_NOT_EQUAL:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must' => [
                                [
                                    "exists" => [
                                        'field' => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
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
                ];

                break;

            default:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must_not' => [
                                [
                                    "exists" => [
                                        "field" => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }
                $query = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
        }
        return $query;
    }

    /**
     * @param mixed $operation
     * @param string|Closure $field
     * @param mixed $value
     * @return void
     */
    public function getQueryBuilderForWhereTerm(mixed $operation, string|Closure $field, mixed $value): void
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
            case self::OPERATOR_NOT_EQUAL:
                if (is_null($value)) {
                    $this->search['query']['bool']['should']
                    [self::MUST_INDEX]['bool']['must'][] = [
                        "exists" => [
                            "field" => $field
                        ]
                    ];

                    break;
                }

                $this->search['query']['bool']['should']
                [self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

                break;
            case self::OPERATOR_EQUAL:
            default:

                if (is_null($value)) {
                    $this->search['query']['bool']['should']
                    [self::MUST_INDEX]['bool']['must'][]['bool']['must_not'][] = [
                        "exists" => [
                            "field" => $field
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
        }
    }

    /**
     * @param array $backtrace
     * @return mixed|string
     */
    public function getParentFunction(array $backtrace): mixed
    {
        return isset($backtrace[1]) ? $backtrace[1]['function'] : '';
    }

    /**
     * @param mixed $operation
     * @param Closure|string $field
     * @param mixed $value
     * @return void
     */
    private function buildQueryForOrWhereTerm(mixed $operation, Closure|string $field, mixed $value): void
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
            case self::OPERATOR_NOT_EQUAL:
                if (is_null($value)) {
                    $this->search['query']['bool']['should'][] = [
                        "exists" => [
                            'field' => $field
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should'][]['bool']['must_not'] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_EQUAL:
            default:
                if (is_null($value)) {
                    $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                        "exists" => [
                            'field' => $field
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should'][] = [
                    "match" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;
        }
    }

    private function isThereNulValueInValues(array $values): bool
    {
        return !empty(array_filter($values, static fn($value) => is_null($value)));
    }

    /**
     * @param mixed $operation
     * @param mixed $value
     * @param Closure|string $field
     * @return array[]|\array[][]
     */
    public function buildOrWhereQueryInClosureState(mixed $operation, mixed $value, Closure|string $field): array
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
            case self::OPERATOR_NOT_EQUAL:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must' => [
                                [
                                    "exists" => [
                                        'field' => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
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
                ];
                break;
            case self::OPERATOR_GT:
                $query = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_GTE:
                $query = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LT:
                $query = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LTE:
                $query = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];
                break;

            case self::OPERATOR_LIKE:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must_not' => [
                                [
                                    "exists" => [
                                        'field' => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;

            case self::OPERATOR_NOT_LIKE:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must' => [
                                [
                                    "exists" => [
                                        'field' => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
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
                ];
                break;

            default:
                if (is_null($value)) {
                    $query = [
                        'bool' => [
                            'must_not' => [
                                [
                                    "exists" => [
                                        'field' => $field
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
                }

                $query = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
        }

        return $query;
    }

    /**
     * @param mixed $operation
     * @param mixed $value
     * @param Closure|string $field
     * @return void
     */
    public function getOrWhereQueryBuilder(mixed $operation, mixed $value, Closure|string $field): void
    {
        switch ($operation) {
            case self::OPERATOR_NOT_EQUAL_SPACESHIP:
            case self::OPERATOR_NOT_EQUAL:
                if (is_null($value)) {
                    $this->search['query']['bool']['should'][] = [
                        "exists" => [
                            "field" => $field
                        ]
                    ];
                    break;
                }
                $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;

            case self::OPERATOR_GT:
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "gt" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_GTE:
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "gte" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LT:
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "lt" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LTE:
                $this->search['query']['bool']['should'][] = [
                    'range' => [
                        $field => [
                            "lte" => $value
                        ]
                    ]
                ];
                break;
            case self::OPERATOR_LIKE:
                if (is_null($value)) {
                    $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                        "exists" => [
                            "field" => $field
                        ]
                    ];
                    break;
                }
                $this->search['query']['bool']['should'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];

                break;

            case self::OPERATOR_NOT_LIKE:
                if (is_null($value)) {
                    $this->search['query']['bool']['should'][] = [
                        "exists" => [
                            "field" => $field
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                    "match_phrase_prefix" => [
                        $field => [
                            'query' => $value
                        ]
                    ]
                ];
                break;

            case self::OPERATOR_EQUAL:
            default:
                if (is_null($value)) {
                    $this->search['query']['bool']['should'][]['bool']['must_not'][] = [
                        "exists" => [
                            "field" => $field
                        ]
                    ];
                    break;
                }

                $this->search['query']['bool']['should'][] = [
                    "term" => [
                        $field => [
                            'value' => $value
                        ]
                    ]
                ];
                break;
        }
    }

    /**
     * @throws IndexNamePatternIsNotValidException
     */
    private function validateIndex(string $index): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,255}$/', $index)) {
            return;
        }

        throw new IndexNamePatternIsNotValidException(
            $index . ' index is not a valid indices name.the valid pattern is /^[a-z][a-z0-9_-]$/'
        );
    }


    /**
     * @throws IndexNamePatternIsNotValidException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function unique(string $field): Collection
    {
        $this->search['collapse'] = [
            'field' => $field
        ];

        return $this->get();
    }

    /**
     * @param string $field
     * @param string|null $sortField
     * @param string $direction
     * @return Collection
     * @throws DirectoryNotFoundException
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function groupBy(string $field, ?string $sortField = null, string $direction = 'asc'): Collection
    {

        if (!in_array($direction, ['asc', 'desc'])) {
            throw new DirectoryNotFoundException();
        }

        $this->search['collapse'] = [
            'field' => $field,
        ];

        $innerHits = $this->generateInnerHits($field, $sortField, $direction);

        $this->search['collapse'] = [
            ...$this->search['collapse'],
            ...$innerHits
        ];


        return $this->get();
    }

    /**
     * @param string $field
     * @param string|null $sortField
     * @param string $direction
     * @return array[]|\array[][]
     */
    public function generateInnerHits(string $field, ?string $sortField = null, string $direction = 'asc'): array
    {
        $innerHits = [
            'inner_hits' => [
                'name' => self::GROUP_BY_PREFIX . $field
            ]
        ];

        if (!$sortField) {
            return $innerHits;
        }

        $innerHits['inner_hits']['sort'] = [
            [
                $sortField => [
                    'order' => $direction
                ]
            ]
        ];

        return $innerHits;
    }

    /**
     * @return bool
     */
    public function isCalledFromGroupBy(): bool
    {
        return isset($this->search['collapse']['inner_hits']);
    }

    /**
     * @param mixed $results
     * @return Collection
     * @throws GuzzleException
     * @throws IndexNamePatternIsNotValidException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function FetchGroupByData(mixed $results): Collection
    {
        $groupByResult = [];

        foreach ($results as $record) {

            $fieldName = array_keys($record['fields'])[self::FIELD_GROUP_BY_INDEX];

            $fieldValue = $record['fields'][$fieldName][self::FIELD_GROUP_BY_VALUE_INDEX];

            $hits = $record['inner_hits'][self::GROUP_BY_PREFIX . $fieldName]['hits']['hits'];

            $groupByCollection = collect();

            foreach ($hits as $hit) {
                $data = [
                    ...$hit[self::SOURCE_KEY],
                    ...['id' => $hit['_id']]
                ];

                $groupByResult[$fieldValue][] = $groupByCollection->add($this->mapResultToModelObject($data));
            }
        }

        return collect($groupByResult);
    }

    private function buildQuery(): void
    {
        foreach ($this->wheres as $condition => $queries) {
            if ($condition === ConditionsEnum::WHERE->value) {
                foreach ($queries as $query) {
                    /**
                     * @type BaseElasticsearchModel $query
                     */
                    if (!$query) {
                        continue;
                    }
                    $query->buildQuery();

                    $this->search['query']['bool']['should'][self::MUST_INDEX]['bool']['must'][] = $query->search['query'];
                }
            } else {
                foreach ($queries as $query) {

                    if (!$query) {
                        continue;
                    }

                    $query->buildQuery();

                    $this->search['query']['bool']['should'][] = $query->search['query'];
                }
            }
        }
    }


}
