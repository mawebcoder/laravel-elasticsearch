<?php

namespace Mawebcoder\Elasticsearch\Exceptions;

use Exception;

class FieldNotDefinedInIndexException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}