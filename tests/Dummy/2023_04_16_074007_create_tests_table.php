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
        $mapper->string(Elasticsearch::FILED_NAME);
        $mapper->integer(Elasticsearch::FILED_AGE);
        $mapper->boolean(Elasticsearch::FILED_IS_ACTIVE);
        $mapper->text(Elasticsearch::FILED_DESCRIPTION);
    }
};
