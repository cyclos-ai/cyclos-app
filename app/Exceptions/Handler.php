<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e): mixed
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'message' => "{$model} not found.",
            ], 404);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($e instanceof AccessDeniedHttpException) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($e instanceof TenantNotFoundException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }

        if ($e instanceof TrackingException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($e instanceof DemurrageCalculationException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($e instanceof WebhookDeliveryException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        // Generic server error — don't leak details in production
        $debug   = config('app.debug', false);
        $message = $debug ? $e->getMessage() : 'An unexpected error occurred.';

        return response()->json([
            'message' => $message,
        ], 500);
    }
}
