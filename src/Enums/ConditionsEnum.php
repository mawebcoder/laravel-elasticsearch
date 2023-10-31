<?php

namespace Mawebcoder\Elasticsearch\Enums;

enum ConditionsEnum: string
{
    case WHERE = 'where';
    case OR_WHERE = 'orWhere';
}
