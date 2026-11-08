<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($exception instanceof ValidationException && ! $request->expectsJson() && $request->hasSession()) {
                $input = $request->session()->get('_old_input', []);
                $safeInput = array_filter($input, fn ($value): bool => is_scalar($value) || $value === null);
                foreach (['weekly_groups', 'weekly_hours'] as $field) {
                    if (isset($input[$field]) && is_array($input[$field])) {
                        $safeInput[$field] = array_map(fn ($slot): array => is_array($slot)
                            ? array_filter($slot, fn ($value): bool => is_scalar($value) || $value === null)
                            : [], $input[$field]);
                    }
                }
                $request->session()->put('_old_input', $safeInput);
            }

            return $response;
        });

        $exceptions->render(function (PDOException $exception, Request $request) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            if (! str_starts_with($sqlState, '08') && ! in_array($driverCode, [2002, 2003, 2006, 2013], true)) {
                return null;
            }

            $message = 'The canteen system cannot connect to its database. Please try again shortly.';
            $headers = ['Retry-After' => '30', 'Cache-Control' => 'no-store'];

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => $message], 503, $headers)
                : response($message, 503, $headers);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
