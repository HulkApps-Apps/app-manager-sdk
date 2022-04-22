<?php

use HulkApps\AppManager\app\Http\Controllers\BannerController;
use HulkApps\AppManager\app\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->group(function () {

    Route::get('marketing-banners', BannerController::class.'@index');

    Route::middleware('app-manager-api')->group(function (){
        Route::get('plan-features', PlanController::class.'@index');
    });
});
