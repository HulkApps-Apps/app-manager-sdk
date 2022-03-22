<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->group(function () {

    Route::get('marketing-banners', function () {
        return "Test";
    });
});