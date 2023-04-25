<?php

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\Test;

return new class extends BaseElasticMigration
{
    public function getModel(): string
    {
        return Test::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->integer('id');
        $mapper->string('name');
        $mapper->boolean('is_active');
        $mapper->text('details');
    }
};
