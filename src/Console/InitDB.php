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
        $database = $db->getConfig('database');
        if(!empty($database)){
            Artisan::call('migrate:fresh', ['--force' => true,'--database' => 'app-manager-failsafe', '--path' => "/vendor/hulkapps/appmanager/migrations"]);
        }
    }

}
