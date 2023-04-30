<?php

use Mawebcoder\Elasticsearch\Migration\AlterElasticIndexMigrationInterface;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\Test;

return new class extends BaseElasticMigration implements AlterElasticIndexMigrationInterface {

    public function alterDown(BaseElasticMigration $baseElasticMigration): void
    {
        // TODO: Implement alterDown() method.
    }

    public function getModel(): string
    {
        return Test::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->dropField('age');
    }
};