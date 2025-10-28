<?php

namespace Hwkdo\IntranetAppMsgraph\Commands;

use Illuminate\Console\Command;

class IntranetAppMsgraphCommand extends Command
{
    public $signature = 'intranet-app-msgraph';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
