<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/kasus');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Diisi di Tahap 3 (daftar kasus, kasus baru, antrean).
    Route::view('/kasus', 'cases.index')->name('cases.index');
});
