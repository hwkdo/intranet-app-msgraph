<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Enums;

enum OnenoteRagStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
