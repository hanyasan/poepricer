<?php

namespace App\Exceptions\Mapping;

use App\Exceptions\AppException;

final class MapInvalidSourceException extends AppException
{
    protected $message = 'Invalid source';
}
