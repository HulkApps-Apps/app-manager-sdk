<?php

namespace HulkApps\AppManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InitDB extends Command
{
    protected $signature = 'app-manager:init-db';

    protected $description = 'Initialize app-manager fail-safe DB.';


    public function handle() {
        $db = DB::connection('app-manager-failsafe');
        $driver = $db->getConfig('driver');
        $database = $db->getConfig('database');
        switch ($driver){
            case "sqlite":
                $disk = Storage::disk('local');
                \File::ensureDirectoryExists(storage_path('app/app-manager'));
                $disk->put('app-manager/database.sqlite','', 'public');
                $database = 'app-manager-sqlite';
                break;
        }

        Artisan::call('migrate', ['--force' => true,'--database' => $database, '--path' => "/vendor/hulkapps/appmanager/migrations"]);
    }

}
