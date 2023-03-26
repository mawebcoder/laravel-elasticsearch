<?php

namespace Mawebcoder\Elasticsearch\Migration;

abstract class ElasticMigration
{
    public array $schema = [];

    /**
     * set model name space to detect model index here
     */
    abstract public function getModel(): string;


    public function integer(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'integer'];
    }

    public function boolean(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'boolean'];
    }

    public function smallInteger(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'short'];
    }

    public function bigInteger(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'long'];
    }

    public function double(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'double'];
    }

    public function float(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'float'];
    }

    public function tinyInt(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'byte'];
    }

    public function string(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'keyword'];
    }

    public function text(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'text'];
    }

    public function datetime(string $field): void
    {
        $this->schema['mapping']['properties'][$field] = ['type' => 'text'];
    }

    public function up(): void
    {
        $this->schema();
        //create index and apply mapper to index while creating the index
    }

    abstract public function schema(): array;
}