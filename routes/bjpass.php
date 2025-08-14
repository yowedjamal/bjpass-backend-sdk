<?php

use Illuminate\Support\Facades\Route;
use BjPass\Http\Controllers\BjPassAuthController;

/*
|--------------------------------------------------------------------------
| BjPass Authentication Routes
|--------------------------------------------------------------------------
|
| These routes are automatically loaded by the BjPassServiceProvider.
| They provide the complete OAuth2/OIDC authentication flow.
|
*/

Route::group([
    'prefix' => config('bjpass.route_prefix', 'auth'),
    'as' => 'bjpass.',
    'middleware' => config('bjpass.route_middleware', ['web'])
], function () {

    // Authentication flow routes
    Route::get('/start', [BjPassAuthController::class, 'start'])->name('start');
    Route::get('/callback', [BjPassAuthController::class, 'callback'])->name('callback');
    Route::get('/error', [BjPassAuthController::class, 'error'])->name('error');

    // API routes for frontend communication
    Route::group([
        'prefix' => 'api',
        'as' => 'api.',
        'middleware' => config('bjpass.api_middleware', ['api'])
    ], function () {
        Route::get('/status', [BjPassAuthController::class, 'status'])->name('status');
        Route::get('/user', [BjPassAuthController::class, 'user'])->name('user');
        Route::post('/logout', [BjPassAuthController::class, 'logout'])->name('logout');
        Route::post('/refresh', [BjPassAuthController::class, 'refresh'])->name('refresh');
        Route::post('/introspect', [BjPassAuthController::class, 'introspect'])->name('introspect');
    });

    // Protected routes example (can be customized)
    Route::group([
        'prefix' => 'protected',
        'as' => 'protected.',
        'middleware' => ['bjpass.auth']
    ], function () {
        Route::get('/dashboard', function () {
            return response()->json([
                'message' => 'Welcome to protected area',
                'user' => request('bjpass_user')
            ]);
        })->name('dashboard');
    });
});
