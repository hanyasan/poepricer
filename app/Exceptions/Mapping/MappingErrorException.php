<?php

namespace App\Exceptions\Mapping;

use App\Exceptions\AppException;

final class MappingErrorException extends AppException
{
    protected $message = 'Mapping error';
}
