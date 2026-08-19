<?php

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
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('student', 'student/*') => route('student.login'),
            $request->is('advisor', 'advisor/*') => route('advisor.login'),
            default => route('login'),
        });

        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('student', 'student/*') => route('student.dashboard'),
            $request->is('advisor', 'advisor/*') => route('advisor.dashboard'),
            default => route('dashboard'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
