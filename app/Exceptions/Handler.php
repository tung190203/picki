<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
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
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // For API routes, always return JSON 401 response
        if ($request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // For web routes, return JSON if expects JSON, otherwise redirect
        return $request->expectsJson()
            ? response()->json(['message' => 'Unauthenticated.'], 401)
            : response()->json(['message' => 'Unauthenticated.'], 401);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle validation exceptions for API routes
        if ($e instanceof ValidationException && $request->is('api/*')) {
            // Get translated error messages - Laravel automatically translates them
            $errors = [];
            foreach ($e->errors() as $key => $messages) {
                $errors[$key] = array_map(function ($message) use ($key) {
                    // If message is still a translation key, translate it with attribute
                    if (str_starts_with($message, 'validation.')) {
                        $attribute = trans('validation.attributes.' . $key, [], 'vi', false) ?: $key;
                        return trans($message, ['attribute' => $attribute], 'vi');
                    }
                    return $message;
                }, $messages);
            }

            $firstError = !empty($errors) ? reset($errors)[0] : 'Validation failed';

            $allErrors = [];
            foreach ($errors as $field => $messages) {
                $allErrors[] = implode(' ', $messages);
            }
            $message = implode(' ', $allErrors);

            return response()->json([
                'status' => false,
                'message' => $firstError,
                'errors' => $errors,
            ], 422);
        }

        return parent::render($request, $e);
    }
}
