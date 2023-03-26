<?php

namespace Mawebcoder\Elasticsearch\Migration;

interface AlterElasticIndexMigrationInterface
{

    public function alterDown(): void;
}