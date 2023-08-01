<?php

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\Elasticsearch;

return new class extends BaseElasticMigration {
    public function getModel(): string
    {
        return Elasticsearch::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->string(Elasticsearch::KEY_NAME);
        $mapper->integer(Elasticsearch::KEY_AGE);
        $mapper->boolean(Elasticsearch::KEY_IS_ACTIVE);
        $mapper->text(Elasticsearch::KEY_DESCRIPTION);
    }
};
