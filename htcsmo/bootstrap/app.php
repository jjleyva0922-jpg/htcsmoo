<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RoleManager;
use App\Http\Middleware\StartApplicationSession;
use App\Support\SessionRecovery;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(replace: [StartSession::class => StartApplicationSession::class]);
        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);
        $middleware->alias([
            'role' => RoleManager::class,
            'account.active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->isMethod('GET') && $request->header('X-Filter-Request') === 'true') {
                return response()->json(['message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
            }
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() || $request->ajax() || $request->hasHeader('X-Workspace-Refresh') || ! $request->isMethod('GET')) {
                return SessionRecovery::response($request, 401);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() === 419) {
                return SessionRecovery::response($request, 419);
            }
        });
    })->create();
