<?php

namespace HulkApps\AppManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InitDB extends Command
{
    protected $signature = 'app-manager:init-db';

    protected $description = 'Initialize app-manager fail-safe DB.';


    public function handle() {

        $db_path = storage_path('app-manager/database.sqlite');
        if (File::exists($db_path)) {
            File::delete($db_path);
        }

        File::put($db_path,'');


        Artisan::call('migrate', array('database' => 'app-manager-sqlite', 'path' => __DIR__.'../../migrations'));

    }

}