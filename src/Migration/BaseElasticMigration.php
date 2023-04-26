<?php

namespace Mawebcoder\Elasticsearch\Migration;

use GuzzleHttp\Exception\GuzzleException;
use http\Client;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mawebcoder\Elasticsearch\Exceptions\FieldTypeIsNotKeyword;
use Mawebcoder\Elasticsearch\Exceptions\InvalidAnalyzerType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidNormalizerTokenFilter;
use Mawebcoder\Elasticsearch\Exceptions\NotValidFieldTypeException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;
use ReflectionException;

abstract class BaseElasticMigration
{


    public array $schema = [];

    public const MAPPINGS = '_mappings';

    public const REINDEX = '_reindex';
    public array $dropMappingFields = [];


    public string $tempIndex;
    public const TYPE_INTEGER = 'integer';
    public const TYPE_TEXT = 'text';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_BIGINT = 'bigint';
    public const TYPE_SMALLINT = 'smallint';
    public const TYPE_TINYINT = 'tinyint';
    public const TYPE_DOUBLE = 'double';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DATETIME = 'datetime';

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

    /**
     * @throws NotValidFieldTypeException
     */
    public function object(string $field, array $options): void
    {
        $values = [];

        foreach ($options as $key => $type) {
            $values['properties'][$key] = $this->setType($type);
        }

        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = [
                ...['type' => 'nested'],
                ...$values
            ];


            return;
        }

        $this->schema['properties'][$field] = $values;
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

        $this->alterIndex();
    }

    public function isCreationState(): bool
    {
        return !$this instanceof AlterElasticIndexMigrationInterface;
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function down(): void
    {
        if ($this->isCreationState()) {
            Elasticsearch::setModel($this->getModel())->delete();
            return;
        }

        $this->alterDown();
    }

    public function dropField(string $field): void
    {
        $this->dropMappingFields[] = $field;
    }


    /**
     * @throws ReflectionException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function alterIndex(): void
    {
        if (!$this->shouldReIndex()) {
            Elasticsearch::setModel($this->getModel())
                ->put(path: self::MAPPINGS, data: $this->schema);
            return;
        }

        $elasticApiService = app(ElasticApiService::class);

        $this->createTempIndex($elasticApiService);

        dd($this->tempIndex);

        $this->reIndexToTempIndex($elasticApiService);

        $currentMappings = $this->getCurrentMappings();

        $newMappings = $this->schema['properties'];

        $this->removeModelIndex($elasticApiService);

        $this->registerModelIndexWithoutChangedFieldTypes(
            elasticApiService: $elasticApiService,
            currentMappings: $currentMappings,
            newMappings: $newMappings
        );

        if ($this->mustDropSomeFields()) {
            $this->removeFieldsThatMustBeRemove($elasticApiService);
        }

        $this->updateModelIndexBaseOnNewChanges(elasticApiService: $elasticApiService, newMappings: $newMappings);

        $this->reIndexFromTempToCurrent($elasticApiService);

        $this->removeTempIndex($elasticApiService);
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function removeFieldsThatMustBeRemove(ElasticApiService $elasticApiService): void
    {
        $script = '';

        foreach ($this->dropMappingFields as $field) {
            $script .= "ctx._source.remove($field);";
        }
        $droppingFieldsSchema = [
            "query" => [
                'bool' => [
                    'must' => []
                ]
            ],
            "script" => trim($script, ';')
        ];

        $response = $elasticApiService->post(
            $this->getModelIndex() . DIRECTORY_SEPARATOR . '_update_by_query',
            $droppingFieldsSchema
        );

        $response->throw();
    }

    public function mustDropSomeFields(): bool
    {
        return !empty($this->dropMappingFields);
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function removeModelIndex(ElasticApiService $elasticApiService): void
    {
        $response = $elasticApiService->delete($this->getModelIndex());

        $response->throw();
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function removeTempIndex(ElasticApiService $elasticApiService): void
    {
        $response = $elasticApiService->delete($this->tempIndex);

        $response->throw();
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function reIndexFromTempToCurrent(ElasticApiService $elasticApiService): void
    {
        $reIndex = [
            "source" => [
                'index' => $this->tempIndex
            ],
            "dest" => [
                "index" => $this->getModelIndex()
            ]
        ];

        $response = $elasticApiService->post('_reindex', $reIndex);

        $response->throw();
    }

    /**
     * @throws RequestException
     * @throws ReflectionException
     */
    public function registerModelIndexWithoutChangedFieldTypes(
        ElasticApiService $elasticApiService,
        array $currentMappings,
        array $newMappings
    ): void {
        $mappings['mappings']['properties'] = [];


        foreach ($currentMappings as $key => $currentMapping) {
            if (array_key_exists($key, $newMappings) || array_key_exists($key, $this->dropMappingFields)) {
                continue;
            }

            $mappings['mappings']['properties'][$key] = $currentMapping;
        }


        $response = $elasticApiService->put($this->getModelIndex(), $mappings);

        $response->throw();
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function updateModelIndexBaseOnNewChanges(ElasticApiService $elasticApiService, array $newMappings): void
    {
        $response = $elasticApiService->put(
            $this->getModelIndex() . DIRECTORY_SEPARATOR . '_mapping',
            ["properties" => $newMappings]
        );

        $response->throw();
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function reIndexToTempIndex(ElasticApiService $elasticApiService): void
    {
        $reIndexData = [
            'source' => [
                'index' => $this->getModelIndex()
            ],
            'dest' => [
                'index' => $this->tempIndex
            ]
        ];

        $response = $elasticApiService->post('_reindex', $reIndexData);

        $response->throw();
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
     * @param ElasticApiService $elasticApiService
     * @return void
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function createTempIndex(ElasticApiService $elasticApiService): void
    {
        $this->tempIndex = strtolower(Str::random(10));

        if (!empty($this->schema)) {
            $elasticApiService->put($this->tempIndex, [
                "mappings" => [
                    "properties" => $this->schema['properties']

                ]
            ]);
        } else {
            $elasticApiService->put($this->tempIndex);
        }
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws ReflectionException
     */
    private function createIndexAndSchema(): void
    {
        Elasticsearch::setModel($this->getModel())
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