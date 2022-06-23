<?php

namespace HulkApps\AppManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class InitDB extends Command
{
    protected $signature = 'app-manager:init-db';

    protected $description = 'Initialize app-manager fail-safe DB.';


    public function handle() {

        $disk = Storage::disk('local');
        if (!$disk->exists('app-manager')) {
            $disk->makeDirectory('app-manager',775);
        }

        $disk->delete('app-manager/database.sqlite');

        $disk->put('app-manager/database.sqlite','');

        Artisan::call('migrate', ['--force' => true,'--database' => 'app-manager-sqlite', '--path' => "/vendor/hulkapps/appmanager/migrations"]);

    }

}
