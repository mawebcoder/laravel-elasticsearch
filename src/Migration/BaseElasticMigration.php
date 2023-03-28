<?php

namespace Mawebcoder\Elasticsearch\Migration;

use Illuminate\Http\Client\RequestException;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use ReflectionException;

abstract class BaseElasticMigration
{
    public array $schema = [];


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

    private function isCreationState(): bool
    {
        return !$this instanceof AlterElasticIndexMigrationInterface;
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

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function down(): void
    {
        if ($this->isCreationState()) {
            Elasticsearch::setModel($this->getModel())->dropModelIndex();
            return;
        }

        /**
         * @todo we need to reindex
         */
        $this->alterDown();
    }

    /**
     * @throws ReflectionException
     * @throws RequestException
     */
    public function alterIndex(): void
    {
        Elasticsearch::setModel($this->getModel())
            ->put(path: '_mappingss', data: $this->schema);
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
}