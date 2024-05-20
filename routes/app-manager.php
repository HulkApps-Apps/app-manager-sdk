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
    Route::post('active-without-plan', PlanController::class.'@activeWithoutPlan');
    Route::post('burst-cache', PlanController::class.'@burstCache');
    Route::post('fail-safe-backup', PlanController::class.'@failSafeBackup');

    Route::get('plan/process/callback', ChargeController::class.'@callback')->name('plan.callback');
    Route::get('plan/process/{plan_id}', ChargeController::class.'@process');
    Route::post('cancel-charge', ChargeController::class.'@cancelCharge');
    Route::post('plan/activate-global', ChargeController::class.'@activateGlobalPlan');
    Route::post('plan/cancel-global', ChargeController::class.'@cancelGlobalPlan');

    Route::middleware('app-manager-api')->group(function (){
        Route::post('store-charge', ChargeController::class.'@storeCharge');
    });
});
