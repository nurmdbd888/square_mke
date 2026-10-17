<?php

namespace App\Console\Commands;

use App\Support\Maintenance\CheckResult;
use App\Support\Maintenance\EnvironmentCheck;
use Illuminate\Console\Command;

class EnvironmentCheckCommand extends Command
{
    protected $signature = 'sdm:check';

    protected $description = 'Show the same environment check as the Maintenance page';

    public function handle(EnvironmentCheck $check): int
    {
        $report = $check->run();

        $this->table(['Status', 'Check', 'Detail'], array_map(
            fn (CheckResult $r) => [strtoupper($r->status), $r->label, $r->detail],
            $report['results'],
        ));

        $this->line('Cron command: '.$report['cron']['command']);

        return EnvironmentCheck::summary($report['results'])[CheckResult::FAIL] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
