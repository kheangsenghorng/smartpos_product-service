<?php

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
            'jwt.auth' => \App\Http\Middleware\JwtAuthMiddleware::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'product.access' => \App\Http\Middleware\EnsureProductAccess::class,
            'sanitize' => \App\Http\Middleware\SanitizeInputMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\SanitizeInputMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 1. Model Not Found Exception (e.g. Product / LabelTemplate not found)
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                $ids = implode(', ', (array) $e->getIds());
                $message = $ids
                    ? "The requested {$model} (ID: {$ids}) was not found."
                    : "The requested {$model} was not found.";

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }
        });

        // 2. HTTP 404 Route / Resource Not Found
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $e->getPrevious();
                if ($previous instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $model = class_basename($previous->getModel());
                    $ids = implode(', ', (array) $previous->getIds());
                    $message = $ids
                        ? "The requested {$model} (ID: {$ids}) was not found."
                        : "The requested {$model} was not found.";
                } else {
                    $message = $e->getMessage() ?: 'The requested endpoint or resource was not found.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }
        });

        // 3. Validation Exception
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please check the submitted fields.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 4. Authentication Exception
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please provide a valid authorization token.',
                ], 401);
            }
        });

        // 5. Authorization / Access Denied Exception
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Forbidden. You do not have permission to access this resource.',
                ], 403);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Forbidden. You do not have permission to access this resource.',
                ], 403);
            }
        });

        // 6. Method Not Allowed Exception
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "The {$request->method()} method is not allowed for this route.",
                ], 405);
            }
        });

        // 7. Database Query Exception
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errorInfo = $e->errorInfo;
                $driverCode = $errorInfo[1] ?? null;

                if ($driverCode === 1062) {
                    $message = 'Duplicate entry. A record with the same unique value already exists.';
                } elseif ($driverCode === 1451 || $driverCode === 1452) {
                    $message = 'Cannot complete operation due to existing related records.';
                } elseif ($driverCode === 1292 || $driverCode === 1366) {
                    $message = 'Invalid data type or format provided for database column.';
                } else {
                    $message = config('app.debug')
                        ? $e->getMessage()
                        : 'A database error occurred while processing your request.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 400);
            }
        });

        // 8. General HTTP Exceptions
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = $e->getStatusCode();
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: (\Symfony\Component\HttpFoundation\Response::$statusTexts[$statusCode] ?? 'HTTP Error'),
                ], $statusCode);
            }
        });
    })->create();
