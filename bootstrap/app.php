<?php

use App\Http\Middleware\LinkPreview;
use App\Http\Middleware\SyncTableLayouts;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Livewire's update requests run in the web group; the panels add it to theirs.
        $middleware->web(append: [SyncTableLayouts::class]);
        // Link previews answer before the sign-in check sends robots to the login page.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, LinkPreview::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
