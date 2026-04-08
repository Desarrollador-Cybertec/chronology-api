<?php

use App\Exceptions\LicenseException;
use App\Exceptions\LicenseSystemUnavailableException;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (LicenseException $e) {
            return response()->json([
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], 403);
        });

        $exceptions->renderable(function (LicenseSystemUnavailableException $e) {
            return response()->json([
                'error_code' => 'license_unavailable',
                'message' => $e->getMessage(),
            ], 503);
        });
    })->create();
