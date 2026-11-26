<?php

namespace App\Console\Commands;

use App\Services\BarangayOfficial\OfficialTermService;
use Illuminate\Console\Command;

class TerminateExpiredBarangayOfficials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'barangay-officials:terminate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate barangay officials whose terms have expired';

    /**
     * Execute the console command.
     */
    public function handle(OfficialTermService $service): int
    {
        $count = $service->terminateExpiredTerms();

        $this->info("Processed {$count} expired official record(s).");

        return self::SUCCESS;
    }
}
