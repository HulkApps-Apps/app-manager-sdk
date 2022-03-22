<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/app-manager')->group(function () {

    Route::get('marketing-banners', function ($type) {

        if ($type === 'header') {
            return "Return header data";
        } else {
            return "Footer data";
        }
    });
});