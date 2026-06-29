<?php

namespace App\Console\Commands;

use App\Services\MikrotikSyncService;
use Illuminate\Console\Command;

class SyncMikrotikCommand extends Command
{
    protected $signature = 'mikrotik:sync';

    protected $description = 'Sync Mikrotik PPPoE sessions, interface stats, and netwatch targets';

    public function handle(MikrotikSyncService $syncService): int
    {
        $syncService->syncAllOrganizations();

        return self::SUCCESS;
    }
}
