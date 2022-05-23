<?php


namespace HulkApps\AppManager\Console;


use HulkApps\AppManager\Client\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncWithAppManager extends Command
{
    protected $signature = 'sync:app-manager';

    protected $description = 'Sync DB with App Manager';

    public function handle() {

        $client = Client::withHeaders(['token' => config('app-manager.secret'), 'Accept' => 'application/json'])->withoutVerifying()
            ->baseUri(config('app-manager.api'));

        $response = $client->get('get-status');
        if ($response->getStatusCode == 200) {
            $charges = DB::connection('app-manager-sqlite')->table('charges')
                ->where('sync', false)->get()->toArray();
            if ($charges) {

            }
        }

    }
}