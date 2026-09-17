<?php

use App\Exceptions\BusinessInputException;
use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\MustChangePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'must_change_password' => MustChangePassword::class,
            'cors' => HandleCors::class,
        ]);
        $middleware->appendToGroup('web', MustChangePassword::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BusinessRuleException $exception, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'BUSINESS_RULE_VIOLATION',
                ], 409);
            }

            return back()->withInput()->withErrors(['business' => $exception->getMessage()]);
        });
        $exceptions->render(function (BusinessInputException $exception, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'INVALID_BUSINESS_INPUT',
                ], 422);
            }

            return back()->withInput()->withErrors(['business' => $exception->getMessage()]);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*') || $request->expectsJson(),
        );
    })->create();
