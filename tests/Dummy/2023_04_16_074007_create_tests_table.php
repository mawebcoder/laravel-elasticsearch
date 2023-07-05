<?php

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\Test;
use Mawebcoder\Elasticsearch\Migration\AlterElasticIndexMigrationInterface;

return new class extends BaseElasticMigration {
    public function getModel(): string
    {
        return Test::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->dropField('name');
        $mapper->boolean('new_field');
    }

    public function alterDown(BaseElasticMigration $mapper): void
    {
        $mapper->string('name');
        $mapper->dropField('new_field');
    }
};
