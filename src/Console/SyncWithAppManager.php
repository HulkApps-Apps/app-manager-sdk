<?php


namespace HulkApps\AppManager\Console;


use HulkApps\AppManager\app\Traits\FailsafeHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncWithAppManager extends Command
{
    use FailsafeHelper;
    protected $signature = 'sync:app-manager';

    protected $description = 'Sync DB with App Manager';

    public function handle() {
        try {
            $this->syncAppManager();
        }
        catch (\Exception $e) {
            report($e);
        }
    }
}
