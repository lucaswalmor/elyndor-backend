<?php

use App\Exceptions\VersaoClienteDesatualizadaException;
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
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->alias([
            'touch.session' => \App\Http\Middleware\TouchSessionPresence::class,
            'deploy.token' => \App\Http\Middleware\ValidateDeployToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (VersaoClienteDesatualizadaException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'code' => 'CLIENTE_DESATUALIZADO',
                'message' => $exception->getMessage(),
                'versao_cliente' => $exception->versaoCliente,
                'versao_exigida' => $exception->versaoExigida,
                'url_download' => $exception->urlDownload,
            ], 426);
        });
    })->create();
