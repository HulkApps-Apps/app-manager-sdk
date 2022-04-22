<?php

use HulkApps\AppManager\app\Http\Controllers\FeaturesController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->group(function () {

    Route::get('marketing-banners', FeaturesController::class.'@index');

    Route::get('plan-features', PlanController::class.'@index');
});
