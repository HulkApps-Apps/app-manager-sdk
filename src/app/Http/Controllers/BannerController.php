<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Illuminate\Routing\Controller;
use function HulkApps\AppManager\app\appManagerCacheData;

class BannerController extends Controller
{
    public function index() {

        $cacheKey = 'app-manager.banners';

        $banners = appManagerCacheData($cacheKey, function () {
            return \AppManager::getBanners();
        });

        return response()->json(['banners' => $banners]);
    }
}