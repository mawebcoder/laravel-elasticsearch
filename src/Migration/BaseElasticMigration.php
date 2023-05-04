<?php

namespace Mawebcoder\Elasticsearch\Migration;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mawebcoder\Elasticsearch\Exceptions\FieldTypeIsNotKeyword;
use Mawebcoder\Elasticsearch\Exceptions\InvalidAnalyzerType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidNormalizerTokenFilter;
use Mawebcoder\Elasticsearch\Exceptions\NotValidFieldTypeException;
use GuzzleHttp\Client;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Jobs\ReIndexMigrationJob;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use ReflectionException;

abstract class BaseElasticMigration
{
    public array $schema = [];

    public const MAPPINGS = '_mappings';

    public array $dropMappingFields = [];

    public readonly string $basePath;

    public ElasticApiService $elasticApiService;

    public readonly Client $client;

    public function __construct()
    {
        $this->basePath = config('elasticsearch.host') . ":" . config('elasticsearch.port');

        $this->client = new Client();

        $this->elasticApiService = new ElasticApiService();
    }

    public string $tempIndex;
    public const TYPE_INTEGER = 'integer';
    public const TYPE_TEXT = 'text';
    public const TYPE_STRING = 'keyword';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_BIGINT = 'long';
    public const TYPE_SMALLINT = 'short';
    public const TYPE_TINYINT = 'byte';
    public const TYPE_DOUBLE = 'double';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DATETIME = 'date';

    public const ANALYZER_STANDARD = 'standard';
    public const ANALYZER_SIMPLE = 'simple';
    public const ANALYZER_WHITESPACE = 'whitespace';
    public const ANALYZER_STOP = 'stop';
    public const ANALYZER_KEYWORD = 'keyword';
    public const ANALYZER_PATTERN = 'pattern';
    public const ANALYZER_FINGERPRINT = 'fingerprint';
    public const ANALYZER_LANGUAGE_ENGLISH = 'english';
    public const ANALYZER_LANGUAGE_PERSIAN = 'persian';
    public const ANALYZER_LANGUAGE_FRENCH = 'french';
    public const ANALYZER_LANGUAGE_ARABIC = 'arabic';
    public const ANALYZER_LANGUAGE_GERMAN = 'german';

    public const NORMALIZER_FILTER_LOWERCASE = 'lowercase';
    public const NORMALIZER_FILTER_UPPERCASE = 'uppercase';
    public const NORMALIZER_FILTER_ASCII_FOLDING = 'asciifolding';
    public const NORMALIZER_FILTER_STOP = 'stop';
    public const NORMALIZER_FILTER_CLASSIC = 'classic';
    public const NORMALIZER_FILTER_NGRAM = 'ngram';
    public const NORMALIZER_FILTER_APOSTROPHE = 'apostrophe';
    public const NORMALIZER_FILTER_SYNONYM = 'synonym';
    public const NORMALIZER_FILTER_STEMMER = 'stemmer';
    public const NORMALIZER_FILTER_LENGTH = 'length';
    public const NORMALIZER_FILTER_SHINGLE = 'shingle';
    public const NORMALIZER_FILTER_UNIQUE = 'unique';
    public const NORMALIZER_FILTER_DECIMAL_DIGIT = 'decimal_digit';
    public const NORMALIZER_FILTER_PERSIAN_NORMALIZATION = 'persian_normalization';
    public const NORMALIZER_FILTER_ARABIC_NORMALIZATION = 'arabic_normalization';

    public const VALID_TYPES = [
        self::TYPE_STRING,
        self::TYPE_TEXT,
        self::TYPE_BOOLEAN,
        self::TYPE_SMALLINT,
        self::TYPE_FLOAT,
        self::TYPE_DOUBLE,
        self::TYPE_DATETIME,
        self::TYPE_TINYINT,
        self::TYPE_BIGINT,
        self::TYPE_INTEGER,
    ];

    public const VALID_ANALYZERS = [
        self::ANALYZER_STANDARD,
        self::ANALYZER_SIMPLE,
        self::ANALYZER_WHITESPACE,
        self::ANALYZER_STOP,
        self::ANALYZER_KEYWORD,
        self::ANALYZER_PATTERN,
        self::ANALYZER_FINGERPRINT,
        self::ANALYZER_LANGUAGE_ENGLISH,
        self::ANALYZER_LANGUAGE_PERSIAN,
        self::ANALYZER_LANGUAGE_FRENCH,
        self::ANALYZER_LANGUAGE_ARABIC,
        self::ANALYZER_LANGUAGE_GERMAN,
    ];

    public const VALID_NORMALIZER_TOKEN_FILTERS = [
        self::NORMALIZER_FILTER_LOWERCASE,
        self::NORMALIZER_FILTER_UPPERCASE,
        self::NORMALIZER_FILTER_ASCII_FOLDING,
        self::NORMALIZER_FILTER_STOP,
        self::NORMALIZER_FILTER_CLASSIC,
        self::NORMALIZER_FILTER_NGRAM,
        self::NORMALIZER_FILTER_APOSTROPHE,
        self::NORMALIZER_FILTER_SYNONYM,
        self::NORMALIZER_FILTER_STEMMER,
        self::NORMALIZER_FILTER_LENGTH,
        self::NORMALIZER_FILTER_SHINGLE,
        self::NORMALIZER_FILTER_UNIQUE,
        self::NORMALIZER_FILTER_DECIMAL_DIGIT,
        self::NORMALIZER_FILTER_PERSIAN_NORMALIZATION,
        self::NORMALIZER_FILTER_ARABIC_NORMALIZATION,
    ];

    /**
     * set model name space to detect model index here
     */
    abstract public function getModel(): string;


    public function integer(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'integer'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'integer'];
    }


    public function object(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'nested'];
            return;
        }
        $this->schema['properties'][$field] = ['type' => 'nested'];
    }

    /**
     * @throws NotValidFieldTypeException
     */
    public function setType(string $type): array
    {
        if (!in_array($type, self::VALID_TYPES)) {
            throw  new  NotValidFieldTypeException();
        }

        return ['type' => $type];
    }

    public function boolean(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'boolean'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'boolean'];
    }

    public function smallInteger(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'short'];
            return;
        }
        $this->schema['properties'][$field] = ['type' => 'short'];
    }

    public function bigInteger(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'long'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'long'];
    }

    public function double(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'double'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'double'];
    }

    public function float(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'float'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'float'];
    }

    public function tinyInt(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'byte'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'byte'];
    }

    public function string(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'keyword'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'keyword'];
    }

    /**
     * @throws InvalidAnalyzerType
     */
    public function text(string $field, ?string $analyzer = null): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'text'];

            if (isset($analyzer)) {
                if (!$this->analyzerIsValid($analyzer)) {
                    throw new InvalidAnalyzerType();
                }

                $this->schema['mappings']['properties'][$field] = [
                    ...$this->schema['mappings']['properties'][$field],
                    ...['analyzer' => $analyzer]
                ];
            }
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'text'];

        if (isset($analyzer)) {
            if (!$this->analyzerIsValid($analyzer)) {
                throw new InvalidAnalyzerType();
            }

            $this->schema['properties'][$field] = [
                ...['analyzer' => $analyzer],
                ... $this->schema['properties'][$field]
            ];
        }
    }

    public function datetime(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'date'];
            return;
        }

        $this->schema['properties'][$field] = ['type' => 'date'];
    }

    /**
     * @throws InvalidNormalizerTokenFilter
     * @throws FieldTypeIsNotKeyword
     */
    public function setNormalizer(string $field, string $tokenFilter, ?string $name = 'custom_normalizer'): void
    {
        if ($this->isCreationState()) {
            $this->normalizerCheckKeywordType($field);

            $this->setNormalizerSettings($tokenFilter, $name);

            $this->schema['mappings']['properties'][$field] = [
                ...$this->schema['mappings']['properties'][$field],
                ...['normalizer' => $name]
            ];

            return;
        }

        $this->normalizerCheckKeywordType($field);

        $this->setNormalizerSettings($tokenFilter, $name);

        $this->schema['properties'][$field] = [
            ...$this->schema['properties'][$field],
            ...['normalizer' => $name]
        ];
    }


    /**
     * @param ElasticApiService $elasticApiService
     * @return void
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function up(): void
    {
        $this->schema($this);

        if ($this->isCreationState()) {
            $this->createIndexAndSchema();
            return;
        }

        if ($this->isThereAnyChangesInFieldsThatShouldBeImplemented() === false) {
            return;
        }


        $this->alterIndex();
    }

    public function isThereAnyChangesInFieldsThatShouldBeImplemented(): bool
    {
        return !empty($this->schema) || !empty($this->dropMappingFields);
    }

    public function isCreationState(): bool
    {
        return !$this instanceof AlterElasticIndexMigrationInterface;
    }

    /**
     * @param ElasticApiService $elasticApiService
     * @param Client $client
     * @return void
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws RequestException
     */
    public function down(): void
    {
        if ($this->isCreationState()) {
            $this->elasticApiService->setModel($this->getModel())->delete();
            return;
        }

        $this->alterDown($this);

        if ($this->isThereAnyChangesInFieldsThatShouldBeImplemented() === false) {
            return;
        }

        $this->alterIndex();
    }

    public function dropField(string $field): void
    {
        $this->dropMappingFields[$field] = $field;
    }


    /**
     * @param ElasticApiService $elasticApiService
     * @return void
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function alterIndex(): void
    {
        if (!$this->shouldReIndex()) {
            $this->elasticApiService->setModel($this->getModel())
                ->put(path: self::MAPPINGS, data: $this->schema);
            return;
        }

        $currentMappings = $this->getCurrentMappings();

        $newMappings = $this->schema['properties'] ?? [];

        $this->createTempIndex();

        $response = $this->reIndexToTempIndex();

        $taskId = $response['task'];

        $this->removeModelIndex();

        $this->registerModelIndexWithoutChangedFieldTypes();

        if (config('elasticsearch.reindex_migration_driver') !== 'queue') {
            dump(
                'DO NOT CANCEL THE OPERATION,OTHERWISE YOUR DATA ON THIS INDEX WILL BE LOST,
            Please wait for reindexing to finish.
            How long it takes depends on your data volume inside  your index'
            );

            while (true) {
                sleep(1);

                $isTaskCompleted = $this->isTaskCompleted(taskId: $taskId);

                if (!$isTaskCompleted) {
                    continue;
                }

                $this->reIndexFromTempToCurrent(
                    currentMappings: $currentMappings,
                    newMappings: $newMappings
                );

                break;
            }

            $this->removeTempIndex();
        } else {
            $queue = config('elasticsearch.reindex_migration_queue_name') ?? 'default';

            ReIndexMigrationJob::dispatch(
                $taskId,
                $currentMappings,
                $newMappings,
                $this->tempIndex,
                $this->getModelIndex(),
                $this->dropMappingFields
            )->onQueue($queue);
        }
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function isTaskCompleted(string $taskId): bool
    {
        $response = $this->elasticApiService->setModel(null)
            ->get('_tasks/' . $taskId);

        return boolval(json_decode($response->getBody(), true)['completed']);
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function removeModelIndex(): void
    {
        $this->elasticApiService
            ->setModel(null)
            ->delete($this->getModelIndex());
    }


    /**
     * @return void
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function removeTempIndex(): void
    {
        $this->elasticApiService
            ->setModel(null)
            ->delete($this->tempIndex);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function reIndexFromTempToCurrent(
        array $currentMappings,
        array $newMappings
    ): void {
        $finalMappings = $currentMappings;

        foreach ($newMappings as $key => $newMapping) {
            if (array_key_exists($key, $finalMappings)) {
                continue;
            }

            $finalMappings[$key] = $newMapping;
        }

        $finalMappings = Arr::except($finalMappings, array_keys($this->dropMappingFields));

        $chosenSource = array_keys(Arr::except($finalMappings, array_keys($this->dropMappingFields)));

        $this->elasticApiService
            ->setModel(null)
            ->put($this->getModelIndex() . DIRECTORY_SEPARATOR . '_mapping', [
                "properties" => $finalMappings
            ]);

        $this->elasticApiService->setModel(null)
            ->post(path: '_reindex', data: [
                "source" => [
                    "index" => $this->tempIndex,
                    "_source" => $chosenSource
                ],
                "dest" => [
                    "index" => $this->getModelIndex()
                ]
            ]);
    }


    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function registerModelIndexWithoutChangedFieldTypes(): void
    {
        $this->elasticApiService->setModel(null)
            ->put($this->getModelIndex());
    }


    /**
     * @param ElasticApiService $elasticApiService
     * @param Client $client
     * @return mixed
     * @throws GuzzleException
     */
    public function reIndexToTempIndex(): mixed
    {
        $reIndexData = [
            'source' => [
                'index' => $this->getModelIndex()
            ],
            'dest' => [
                'index' => $this->tempIndex
            ]
        ];

        $path = trim($this->basePath, '/') .
            '/' . '_reindex?wait_for_completion=false';

        $response = $this->client->post($path, [
            'json' => $reIndexData
        ]);

        return json_decode($response->getBody(), true);
    }

    public function getModelIndex(): string
    {
        $model = $this->getModel();

        /**
         * @type BaseElasticsearchModel $modelObject
         */
        $modelObject = new $model();

        return $modelObject->getIndex();
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function getCurrentMappings(): array
    {
        $model = $this->getModel();

        /**
         * @type BaseElasticsearchModel $modelInstance
         */
        $modelInstance = new $model();

        return $modelInstance->getMappings();
    }


    /**
     * @param Client $client
     * @return void
     * @throws GuzzleException
     */
    public function createTempIndex(): void
    {
        $this->tempIndex = strtolower(Str::random(10));

        $path = trim($this->basePath, '/') .
            '/' .
            $this->tempIndex;

        $this->client->put($path);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws ReflectionException
     */
    private function createIndexAndSchema(): void
    {
        $this->elasticApiService->setModel($this->getModel())
            ->put(data: $this->schema);
    }

    abstract public function schema(BaseElasticMigration $mapper);

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    private function shouldReIndex(): bool
    {
        return !empty($this->getUpdatingFields()) || !empty($this->dropMappingFields);
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function getUpdatingFields(): array
    {
        $model = $this->getModel();

        /**
         * @type BaseElasticsearchModel $model
         */
        $model = new $model();

        $oldFields = $model->getFields();

        if (!isset($this->schema['properties'])) {
            return [];
        }

        $newFields = array_keys($this->schema['properties']);

        return array_intersect($oldFields, $newFields);
    }

    private function analyzerIsValid(string $analyzer): bool
    {
        return in_array($analyzer, self::VALID_ANALYZERS);
    }

    private function normalizerFilterIsValid(string $filter): bool
    {
        return in_array($filter, self::VALID_NORMALIZER_TOKEN_FILTERS);
    }

    /**
     * @param string $tokenFilter
     * @param string $name
     * @return void
     * @throws InvalidNormalizerTokenFilter
     */
    private function setNormalizerSettings(string $tokenFilter, string $name): void
    {
        if (!$this->normalizerFilterIsValid($tokenFilter)) {
            throw new InvalidNormalizerTokenFilter();
        }

        $this->schema['settings']['analysis']['normalizer'][$name] = [
            'type' => 'custom',
            'char_filter' => [
                ...$this->schema['settings']['analysis']['normalizer'][$name]['char_filter'],
                ...[]
            ],
            'filter' => [
                ...$this->schema['settings']['analysis']['normalizer'][$name]['filter'],
                ...[$tokenFilter]
            ]
        ];
    }

    /**
     * @throws FieldTypeIsNotKeyword
     */
    private function normalizerCheckKeywordType(string $field): void
    {
        if ($this->schema['mappings']['properties'][$field]['type'] !== self::ANALYZER_KEYWORD) {
            throw new FieldTypeIsNotKeyword(message: 'normalizer must be defined for keyword (string) fields only.');
        }
    }
}