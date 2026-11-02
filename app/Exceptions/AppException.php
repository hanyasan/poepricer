<?php

namespace App\Exceptions;

abstract class AppException extends \Exception
{
    protected $code = 500;

    protected $message = 'Internal Server Error';
}
