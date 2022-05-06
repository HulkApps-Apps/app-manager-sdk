<?php

use HulkApps\AppManager\app\Http\Controllers\BannerController;
use HulkApps\AppManager\app\Http\Controllers\ChargeController;
use HulkApps\AppManager\app\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->as('app-manager.')->group(function () {

    Route::get('marketing-banners', BannerController::class.'@index');

    Route::get('plan-features', PlanController::class.'@index');
    Route::get('plans', PlanController::class.'@plans');
    Route::get('users', PlanController::class.'@users');
    Route::get('active-without-plan', PlanController::class.'@activeWithoutPlan');

    Route::get('plan/process/{plan_id}', ChargeController::class.'@process');
    Route::get('plan/process/callback', ChargeController::class.'@callback')->name('plan.callback');

    Route::middleware('app-manager-api')->group(function (){
        Route::post('store-charge', ChargeController::class.'@storeCharge');
    });
});
