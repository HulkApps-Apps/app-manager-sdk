<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class BannerController extends Controller
{
    public function index() {

        $banners = Cache::get('banners', function () {
            $response = \AppManager::getBanners();
//			Cache::tags('app-manager-banners')->put('banners', $response, Carbon::now()->addDay());
            Cache::put('banners', $response, Carbon::now()->addDay());
            return $response;
        });

        return response()->json(['banners' => $banners]);
    }
}