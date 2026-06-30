<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Controllers\Api\FormImportController;
use App\Http\Controllers\Api\FormPublishController;
use App\Http\Controllers\Api\MobileBrandingController;
use App\Http\Controllers\Api\MobileConfigController;
use App\Http\Controllers\Api\MobileSubmissionController;
use App\Http\Controllers\Api\MobileAuthController;

Route::middleware(SecurityHeaders::class)->group(function () {
    Route::get('/mobile/branding', MobileBrandingController::class)->middleware('throttle:30,1');

    // Login is deliberately throttled to slow down password guessing attacks.
    Route::post('/mobile/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:5,1');

    // Mobile endpoints require a Sanctum token; keep public routes outside this group rare.
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/mobile/auth/me', [MobileAuthController::class, 'me']);
        Route::post('/mobile/auth/logout', [MobileAuthController::class, 'logout']);
        Route::get('/mobile/config', MobileConfigController::class);
        Route::post('/mobile/submissions/batch', MobileSubmissionController::class);
    });

    // Form management changes server state, so it must be limited to authenticated admins.
    Route::middleware(['auth:sanctum', 'admin', 'throttle:10,1'])->group(function () {
        Route::post('/forms/import-xlsform', FormImportController::class);
        Route::post('/forms/{formId}/versions/{version}/publish', FormPublishController::class)
            ->where(['formId' => '[A-Za-z0-9._-]+', 'version' => '[0-9]+']);
    });
});
