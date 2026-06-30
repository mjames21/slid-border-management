<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Console\Commands\CreateAdminUserCommand;
use App\Console\Commands\ImportXlsFormFixtureCommand;
use App\Http\Middleware\AdminMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $trustedHosts = array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('TRUSTED_HOSTS', ''))
        )));

        if ($trustedHosts !== []) {
            $middleware->trustHosts(at: fn (): array => $trustedHosts, subdomains: false);
        } else {
            $middleware->trustHosts();
        }

        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withCommands([
        CreateAdminUserCommand::class,
        ImportXlsFormFixtureCommand::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'two_factor_code',
            'recovery_code',
        ]);

        $exceptions->truncateRequestExceptionsAt(1024);
    })->create();
