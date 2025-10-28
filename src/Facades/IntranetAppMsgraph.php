<?php

namespace Hwkdo\IntranetAppMsgraph\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppMsgraph\IntranetAppMsgraph
 */
class IntranetAppMsgraph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppMsgraph\IntranetAppMsgraph::class;
    }
}
