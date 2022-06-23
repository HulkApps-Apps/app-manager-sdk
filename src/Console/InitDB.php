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
        \File::ensureDirectoryExists('storage/app/app-manager');

        $disk->delete('app-manager/database.sqlite');

        $disk->put('app-manager/database.sqlite','', 'public');

        Artisan::call('migrate', ['--force' => true,'--database' => 'app-manager-sqlite', '--path' => "/vendor/hulkapps/appmanager/migrations"]);

    }

}
