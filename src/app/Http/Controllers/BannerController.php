<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Illuminate\Routing\Controller;

class BannerController extends Controller
{
    public function index() {

        $banners = \AppManager::getBanners();

        return response()->json(['banners' => $banners]);
    }
}