<?php

namespace Mawebcoder\Elasticsearch\Models;

abstract class BaseElasticsearchModel
{

    public array $search = [
        "query" => [
            "sort" => [],
            "bool" => [
                "must" => [
                ],
            ]
        ]
    ];

    abstract public function getIndex(): string;

}