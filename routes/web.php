<?php

use App\Http\Controllers\StatusController;
use App\Http\Middleware\BasicAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(BasicAuth::class)->group(function () {
    Route::get('/', [StatusController::class, 'index'])->name('status');
    Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');
});
