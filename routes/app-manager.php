<?php

use HulkApps\AppManager\app\Http\Controllers\BannerController;
use HulkApps\AppManager\app\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->group(function () {

    Route::get('marketing-banners', BannerController::class.'@index');

    Route::get('plan-features', PlanController::class.'@index');
    Route::get('plans', PlanController::class.'@plans');

    Route::middleware('app-manager-api')->group(function (){
        Route::post('store-charge', PlanController::class.'@storeCharge');
    });
});
