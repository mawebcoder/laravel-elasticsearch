<?php

namespace Mawebcoder\Elasticsearch\Exceptions;

use Exception;

class CanNotCreateTheFileException extends Exception
{
    protected $message = 'Can not create the file';
}