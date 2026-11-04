<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaseController;
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

    // Setiap akses halaman kasus tercatat di audit_logs, termasuk yang ditolak.
    Route::middleware('audit')->group(function () {
        Route::get('/kasus', [CaseController::class, 'index'])->name('cases.index');

        Route::get('/kasus/baru', [CaseController::class, 'create'])
            ->middleware(['mode.local', 'can:collect-data'])
            ->name('cases.create');

        Route::get('/kasus/{case}', [CaseController::class, 'show'])
            ->whereNumber('case')
            ->middleware('can:view,case')
            ->name('cases.show');
    });

    // Pencatatan audit untuk store dilakukan di controller, karena ID kasus baru ada setelah disimpan.
    Route::post('/kasus', [CaseController::class, 'store'])
        ->middleware(['mode.local', 'can:collect-data', 'throttle:10,1'])
        ->name('cases.store');
});
