<?php

return [
    'host' => 'http://localhost',
    'port' => 9200,
    'reindex_migration_driver' => "sync", //sync or queue,
    "reindex_migration_queue_name" => 'default'
];