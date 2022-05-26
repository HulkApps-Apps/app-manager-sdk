<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class BannerController extends Controller
{
    public function index() {

        $banners = Cache::rememberForever('app-manager.banners', function () {
            return \AppManager::getBanners();
        });

        return response()->json(['banners' => $banners]);
    }
}