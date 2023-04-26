<?php

namespace Mawebcoder\Elasticsearch\Migration;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
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

    public function text(string $field): void
    {
        if ($this->isCreationState()) {
            $this->schema['mappings']['properties'][$field] = ['type' => 'text'];
            return;
        }
        $this->schema['properties'][$field] = ['type' => 'text'];
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
     * @throws ReflectionException
     * @throws RequestException
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

        $this->reIndexToTempIndex($elasticApiService);

        $currentMappings = $this->getCurrentMappings();

        $newMappings = $this->schema['properties'];

        $this->removeModelIndex($elasticApiService);

        $this->registerModelIndexWithoutChangedFieldTypes(
            elasticApiService: $elasticApiService,
            currentMappings: $currentMappings,
            newMappings: $newMappings
        );

        $this->updateModelIndexBaseOnNewChanges(elasticApiService: $elasticApiService, newMappings: $newMappings);

        $this->reIndexFromTempToCurrent($elasticApiService);

        $this->removeTempIndex($elasticApiService);

        Elasticsearch::setModel($this->getModel())
            ->put(path: self::MAPPINGS, data: $this->schema);
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
            if (array_key_exists($key, $newMappings)) {
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
        $mappings = [
            "properties" => $newMappings
        ];

        $response = $elasticApiService->put($this->getModelIndex() . DIRECTORY_SEPARATOR . '_mapping', $newMappings);

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
     * @throws RequestException
     * @throws ReflectionException
     */
    public function createTempIndex(ElasticApiService $elasticApiService): void
    {
        $this->tempIndex = Str::random(20);

        $response = $elasticApiService->put($this->tempIndex);

        $response->throw();

        $response = $elasticApiService->put($this->tempIndex . DIRECTORY_SEPARATOR . self::MAPPINGS, $this->schema);

        $response->throw();
    }

    /**
     * @throws RequestException
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
        return !empty($this->getUpdatingFields());
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

        $newFields = array_keys($this->schema['properties']);

        return array_intersect($oldFields, $newFields);
    }
}