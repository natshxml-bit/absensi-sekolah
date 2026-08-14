<?php

use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\OutsideRadiusException;
use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (OutsideRadiusException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (AlreadyCheckedInException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        });
    })->create();
