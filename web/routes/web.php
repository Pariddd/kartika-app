<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseReportController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\UploadController;
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

        Route::get('/kasus/{case}/jawaban', [CaseController::class, 'answer'])
            ->whereNumber('case')
            ->middleware('can:view,case')
            ->name('cases.answer');

        Route::get('/kasus/{case}/ringkasan', [CaseReportController::class, 'show'])
            ->whereNumber('case')
            ->middleware('can:view,case')
            ->name('cases.report');

        // scopeBindings: klaster wajib milik kasus di URL, jika tidak 404.
        Route::get('/kasus/{case}/klaster/{cluster}', [ClusterController::class, 'show'])
            ->whereNumber(['case', 'cluster'])
            ->middleware('can:view,case')
            ->scopeBindings()
            ->name('clusters.show');

        // Log audit: hanya admin. Akses yang ditolak juga tercatat.
        Route::get('/audit', [AuditLogController::class, 'index'])
            ->middleware('can:view-audit-log')
            ->name('audit.index');
    });

    Route::post('/kasus/{case}/klaster/{cluster}/penilaian', [ClusterController::class, 'review'])
        ->whereNumber(['case', 'cluster'])
        ->middleware(['can:view,case', 'throttle:30,1'])
        ->scopeBindings()
        ->name('clusters.review');

    // Coba Sendiri: unggah CSV milik pengguna (semua peran, kedua mode).
    Route::get('/coba', [UploadController::class, 'create'])->middleware('audit')->name('upload.create');
    Route::get('/coba/contoh.csv', [UploadController::class, 'sample'])->name('upload.sample');
    Route::post('/coba', [UploadController::class, 'store'])->middleware('throttle:5,1')->name('upload.store');

    // Pencatatan audit untuk store dilakukan di controller, karena ID kasus baru ada setelah disimpan.
    Route::post('/kasus', [CaseController::class, 'store'])
        ->middleware(['mode.local', 'can:collect-data', 'throttle:10,1'])
        ->name('cases.store');
});
