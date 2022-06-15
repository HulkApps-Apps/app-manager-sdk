<?php

namespace HulkApps\AppManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InitDB extends Command
{
    protected $signature = 'app-manager:init-db';

    protected $description = 'Initialize app-manager fail-safe DB.';


    public function handle() {

        $db_path = storage_path('app-manager/database.sqlite');
        if (!\Storage::exists('app-manager')) {
            \Storage::makeDirectory('app-manager',775);
        }

        \Storage::put('app-manager/database.sqlite','');

        Artisan::call('migrate', ['--force' => true,'--database' => 'app-manager-sqlite', '--path' => "/vendor/hulkapps/appmanager/migrations"]);

    }

}
