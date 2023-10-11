<?php

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Migration\AlterElasticIndexMigrationInterface;
use Tests\DummyRequirements\Models\EUserModel;

return new class extends BaseElasticMigration implements AlterElasticIndexMigrationInterface {

    public function alterDown(BaseElasticMigration $mapper): void
    {
        $mapper->dropField('city');
    }

    public function getModel(): string
    {
        return EUserModel::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->string('city');
    }
};