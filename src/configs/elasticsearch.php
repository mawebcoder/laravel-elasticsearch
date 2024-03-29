<?php

return [
    'index_prefix' => env('APP_NAME'),
    'host' => 'http://localhost',
    'port' => 9200,
    'ssl' => env('GUZZLE_SSL_VERIFICATION', false),
    'reindex_migration_driver' => "sync", //sync or queue,
    "reindex_migration_queue_name" => 'default',
    'base_migrations_path' => app_path('Elasticsearch/Migrations'),
    'base_models_path' => app_path('Elasticsearch/Models'),
    "username" => env('ELASTICSEARCH_USERNAME', null),
    'password' => env('ELASTICSEARCH_PASSWORD', null)
];