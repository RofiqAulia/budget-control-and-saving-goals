<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Telegram posts updates without a session or CSRF token.
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

/*
 * Shared hosting layout: the web root sits beside the app (public_html), not
 * inside it. Prefer it whenever it exists so @vite, asset() and the served
 * files always come from the same place.
 */
$sharedWebRoot = dirname(__DIR__, 2).'/public_html';

if (is_dir($sharedWebRoot)) {
    $app->usePublicPath($sharedWebRoot);
}

return $app;
