<?php

use HulkApps\AppManager\app\Http\Controllers\BannerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->group(function () {

    Route::get('marketing-banners', BannerController::class.'@index');
});