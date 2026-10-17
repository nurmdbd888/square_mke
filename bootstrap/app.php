<?php

use App\Http\Middleware\EnsureAccountReady;
use App\Http\Middleware\EnsureAppKey;
use App\Http\Middleware\MaintenanceSession;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\Maintenance\MaintenanceGate;
use App\Support\SharedPath;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('maintenance')->group(__DIR__.'/../routes/maintenance.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureAppKey::class);
        $middleware->encryptCookies(except: [SetLocale::COOKIE, MaintenanceGate::COOKIE]);
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);
        $middleware->alias(['account.ready' => EnsureAccountReady::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // No CSRF middleware, and the session only when APP_KEY is valid:
        // the Maintenance page must work before APP_KEY and the database exist.
        $middleware->group('maintenance', [
            MaintenanceSession::class,
            SubstituteBindings::class,
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

$shared = SharedPath::resolve(
    $app->basePath(),
    $_SERVER['SDM_SHARED_PATH'] ?? (getenv('SDM_SHARED_PATH') ?: null),
);

if ($shared !== null) {
    $app->useEnvironmentPath($shared);
    $app->useStoragePath($shared.'/storage');
}

return $app;
