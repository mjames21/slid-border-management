<?php

use App\Http\Controllers\Web\AdminBorderPostController;
use App\Http\Controllers\Web\AdminCountryController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminDeploymentRequestController;
use App\Http\Controllers\Web\AdminFormController;
use App\Http\Controllers\Web\AdminLocationController;
use App\Http\Controllers\Web\AdminMapController;
use App\Http\Controllers\Web\AdminSubmissionController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\AdminWebhookController;
use App\Http\Controllers\Web\DeploymentRequestController;
use App\Http\Controllers\Web\WebFormController;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(SecurityHeaders::class)->group(function () {
    Route::get('/', fn () => view('welcome'))->name('welcome');
    Route::get('/get-started', fn () => view('get-started'))->name('get-started');
    Route::post('/deployment-requests', [DeploymentRequestController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('deployment-requests.store');

    Route::middleware('guest')->group(function () {
        Route::get('/admin/login', fn () => redirect()->route('login'))->name('admin.login');
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('/collect/forms/{form}', [WebFormController::class, 'show'])
            ->whereNumber('form')
            ->name('collect.forms.show');
        Route::post('/collect/forms/{form}', [WebFormController::class, 'store'])
            ->whereNumber('form')
            ->middleware('throttle:20,1')
            ->name('collect.forms.store');
    });

    // Every admin page needs both authentication and the admin role check.
    Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/data', [AdminDashboardController::class, 'data'])->middleware('throttle:120,1')->name('dashboard.data');
        Route::post('/dashboard/views', [AdminDashboardController::class, 'storeView'])->middleware('throttle:30,1')->name('dashboard.views.store');
        Route::delete('/dashboard/views/{dashboardView}', [AdminDashboardController::class, 'destroyView'])->middleware('throttle:30,1')->name('dashboard.views.destroy');
        Route::get('/projects', [AdminFormController::class, 'index'])->name('projects.index');
        Route::get('/forms', [AdminFormController::class, 'index'])->name('forms.index');
        Route::get('/forms/create', [AdminFormController::class, 'create'])->name('forms.create');
        Route::post('/forms', [AdminFormController::class, 'store'])->middleware('throttle:10,1')->name('forms.store');
        Route::get('/forms/builder/new', [AdminFormController::class, 'builder'])->name('forms.builder');
        Route::post('/forms/builder', [AdminFormController::class, 'storeBuilder'])->middleware('throttle:10,1')->name('forms.builder.store');
        Route::post('/forms/templates/{template}/clone', [AdminFormController::class, 'cloneTemplate'])
            ->whereNumber('template')
            ->middleware('throttle:10,1')
            ->name('forms.templates.clone');
        Route::get('/forms/{form}', [AdminFormController::class, 'show'])->whereNumber('form')->name('forms.show');
        Route::get('/forms/{form}/builder', [AdminFormController::class, 'editBuilder'])->whereNumber('form')->name('forms.builder.edit');
        Route::post('/forms/{form}/builder', [AdminFormController::class, 'updateBuilder'])->whereNumber('form')->middleware('throttle:10,1')->name('forms.builder.update');
        Route::post('/forms/{form}/versions/{version}/publish', [AdminFormController::class, 'publish'])
            ->whereNumber(['form', 'version'])
            ->middleware('throttle:10,1')
            ->name('forms.versions.publish');
        Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/export/csv', [AdminSubmissionController::class, 'exportCsv'])->name('submissions.export.csv');
        Route::get('/submissions/export/json', [AdminSubmissionController::class, 'exportJson'])->name('submissions.export.json');
        Route::get('/submissions/{submission}', [AdminSubmissionController::class, 'show'])->whereNumber('submission')->name('submissions.show');
        Route::get('/map', [AdminMapController::class, 'index'])->name('map.index');
        Route::get('/integrations/webhooks', [AdminWebhookController::class, 'index'])->name('webhooks.index');
        Route::post('/integrations/webhooks', [AdminWebhookController::class, 'store'])->middleware('throttle:10,1')->name('webhooks.store');
        Route::post('/integrations/webhooks/{webhook}/toggle', [AdminWebhookController::class, 'toggle'])
            ->whereNumber('webhook')
            ->middleware('throttle:30,1')
            ->name('webhooks.toggle');
        Route::post('/integrations/webhook-deliveries/{delivery}/retry', [AdminWebhookController::class, 'retry'])
            ->whereNumber('delivery')
            ->middleware('throttle:30,1')
            ->name('webhook-deliveries.retry');
        Route::get('/locations', [AdminLocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [AdminLocationController::class, 'store'])->middleware('throttle:10,1')->name('locations.store');
        Route::get('/countries', [AdminCountryController::class, 'index'])->name('countries.index');
        Route::get('/countries/{country}/edit', [AdminCountryController::class, 'edit'])->name('countries.edit');
        Route::post('/countries/{country}', [AdminCountryController::class, 'update'])->middleware('throttle:10,1')->name('countries.update');
        Route::post('/countries/{country}/boundary', [AdminCountryController::class, 'updateBoundary'])->middleware('throttle:10,1')->name('countries.boundary.update');
        Route::get('/deployment-requests', [AdminDeploymentRequestController::class, 'index'])->name('deployment-requests.index');
        Route::post('/deployment-requests/{deploymentRequest}', [AdminDeploymentRequestController::class, 'update'])
            ->whereNumber('deploymentRequest')
            ->middleware('throttle:30,1')
            ->name('deployment-requests.update');
        Route::get('/border-posts', [AdminBorderPostController::class, 'index'])->name('border-posts.index');
        Route::get('/border-posts/create', [AdminBorderPostController::class, 'create'])->name('border-posts.create');
        Route::post('/border-posts', [AdminBorderPostController::class, 'store'])->middleware('throttle:10,1')->name('border-posts.store');
        Route::get('/border-posts/{borderPost}/edit', [AdminBorderPostController::class, 'edit'])->whereNumber('borderPost')->name('border-posts.edit');
        Route::post('/border-posts/{borderPost}', [AdminBorderPostController::class, 'update'])->whereNumber('borderPost')->middleware('throttle:10,1')->name('border-posts.update');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->middleware('throttle:10,1')->name('users.store');
        Route::get('/users/{user}/setup-qr', [AdminUserController::class, 'setupQr'])->whereNumber('user')->name('users.setup-qr');
        Route::post('/users/{user}/setup-qr', [AdminUserController::class, 'generateSetupQr'])->whereNumber('user')->middleware('throttle:10,1')->name('users.setup-qr.generate');
    });
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route(auth()->user()?->is_admin ? 'admin.projects.index' : 'profile.show');
    })->name('dashboard');
});
