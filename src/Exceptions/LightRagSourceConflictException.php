<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Exceptions;

use RuntimeException;

class LightRagSourceConflictException extends RuntimeException
{
    public function __construct(public readonly string $fileSource)
    {
        parent::__construct('LightRAG enthält bereits '.$fileSource.'.');
    }
}
