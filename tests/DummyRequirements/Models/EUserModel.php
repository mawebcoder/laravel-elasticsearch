<?php

namespace Tests\DummyRequirements\Models;

use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

class EUserModel extends BaseElasticsearchModel
{
   public const INDEX_NAME = 'user_test';

    public const KEY_NAME = 'name';
    public const KEY_IS_ACTIVE = 'is_active';
    public const KEY_DESCRIPTION = 'description';
    public const   KEY_INFORMATION='information';
    public const KEY_AGE = 'age';

    public function getIndex(): string
    {
        return self::INDEX_NAME;
    }
}