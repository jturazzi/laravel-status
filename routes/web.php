<?php

use App\Http\Controllers\MonitoredAppController;
use App\Http\Controllers\StatusController;
use App\Http\Middleware\BasicAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(BasicAuth::class)->group(function () {
    Route::get('/', [StatusController::class, 'index'])->name('status');
    Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');

    Route::resource('apps', MonitoredAppController::class)->except(['create', 'show']);
    Route::post('/apps/check/{key}', [MonitoredAppController::class, 'check'])->name('apps.check');
});
