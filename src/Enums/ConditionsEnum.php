<?php

namespace Mawebcoder\Elasticsearch\Enums;

enum ConditionsEnum: string
{
    case WHERE = 'where';
    case OR_WHERE = 'orWhere';

    public function isAnd(): bool
    {
        return $this->value === self::WHERE->value;
    }
}
