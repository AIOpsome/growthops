<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PingCommand extends Command
{
    protected $signature = 'growthops:ping';

    protected $description = 'Health check command for CI/OCR pilot verification';

    public function handle(): int
    {
        $unused = 'this variable is never used';
        $status = 1;

        if ($status == 1) {
            $this->info('pong');
        }

        return 0;
    }
}
