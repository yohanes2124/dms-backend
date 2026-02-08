<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\QueryException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle API requests
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with consistent JSON responses
     */
    private function handleApiException(Request $request, Throwable $e)
    {
        // Log the exception with context
        $this->logException($e, $request);

        // JWT Exceptions
        if ($e instanceof TokenExpiredException) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired',
                'error_code' => 'TOKEN_EXPIRED'
            ], 401);
        }

        if ($e instanceof TokenInvalidException) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid',
                'error_code' => 'TOKEN_INVALID'
            ], 401);
        }

        if ($e instanceof JWTException) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required',
                'error_code' => 'TOKEN_REQUIRED'
            ], 401);
        }

        // Authentication Exception
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Validation Exception
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_FAILED'
            ], 422);
        }

        // Model Not Found
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'error_code' => 'RESOURCE_NOT_FOUND'
            ], 404);
        }

        // Route Not Found
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
                'error_code' => 'ENDPOINT_NOT_FOUND'
            ], 404);
        }

        // Method Not Allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'error_code' => 'METHOD_NOT_ALLOWED'
            ], 405);
        }

        // Database Query Exception
        if ($e instanceof QueryException) {
            $message = config('app.debug') ? $e->getMessage() : 'Database error occurred';
            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        }

        // Generic Exception
        $message = config('app.debug') ? $e->getMessage() : 'An error occurred';
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'INTERNAL_ERROR'
        ], $statusCode);
    }

    /**
     * Log exception with request context
     */
    private function logException(Throwable $e, Request $request)
    {
        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'request_id' => $request->header('X-Request-ID', uniqid()),
        ];

        // Don't log sensitive data
        $input = $request->except(['password', 'password_confirmation', 'current_password']);
        if (!empty($input)) {
            $context['input'] = $input;
        }

        logger()->error($e->getMessage(), array_merge($context, [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]));
    }
}