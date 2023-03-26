<?php

namespace Mawebcoder\Elasticsearch\Models;

abstract class Elasticsearch
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


    public function generateIndex()
    {

    }


}