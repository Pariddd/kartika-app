<?php

use App\Http\Middleware\EnsureLocalMode;
use App\Http\Middleware\RecordAuditLog;
use App\Http\Middleware\SecurityHeaders;
use App\Support\UploadLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'mode.local' => EnsureLocalMode::class,
            'audit' => RecordAuditLog::class,
        ]);

        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('cases.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            return back()->withErrors([
                'import_file' => 'File terlalu besar untuk batas unggahan PHP di komputer ini ('.UploadLimit::label().'). '
                    .'Naikkan upload_max_filesize dan post_max_size di php.ini, atau perkecil jumlah item di Apify.',
            ]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
