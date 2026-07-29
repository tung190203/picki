<?php

namespace App\Exceptions;

use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\User;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\Participant;
use App\Models\MiniParticipant;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Club\ClubActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
                'success' => false,
                'message' => $firstError,
                'errors' => $errors,
            ], 422);
        }

        // Handle BusinessException for API routes
        if ($e instanceof BusinessException && $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpCode());
        }

        // Handle ModelNotFoundException for API routes - return friendly message
        if ($e instanceof ModelNotFoundException && $request->is('api/*')) {
            $modelClass = $e->getModel();

            $friendlyName = $this->getFriendlyModelName($modelClass);
            $modelId = $this->getModelIdFromException($e);

            $message = "Không tìm thấy {$friendlyName}" . ($modelId ? " với ID: {$modelId}" : '');

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 404);
        }

        // Handle NotFoundHttpException for API routes
        if ($e instanceof NotFoundHttpException && $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tài nguyên này.',
            ], 404);
        }

        // Handle AuthorizationException / AccessDeniedHttpException for API routes
        if (($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) && $request->is('api/*')) {
            $message = $e->getMessage() ?: 'Bạn không có quyền thực hiện hành động này.';
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        // Handle generic HTTP exceptions for API routes
        if ($e instanceof HttpException && $request->is('api/*')) {
            $message = $this->getHttpExceptionMessage($e->getStatusCode());
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e->getStatusCode());
        }

        return parent::render($request, $e);
    }

    /**
     * Get friendly model name for error messages
     */
    protected function getFriendlyModelName(string $modelClass): string
    {
        $modelNames = [
            MiniTournament::class => 'kèo đấu',
            Tournament::class => 'giải đấu',
            Team::class => 'đội',
            Club::class => 'clb',
            User::class => 'người dùng',
            Participant::class => 'người tham gia',
            MiniParticipant::class => 'người tham gia',
            MiniMatch::class => 'trận đấu',
            Matches::class => 'trận đấu',
            ClubMember::class => 'thành viên clb',
            ClubActivity::class => 'hoạt động clb',
        ];

        return $modelNames[$modelClass] ?? class_basename($modelClass);
    }

    /**
     * Extract model ID from ModelNotFoundException
     */
    protected function getModelIdFromException(ModelNotFoundException $e): ?string
    {
        // The exception message format: "No query results for model [Class] [id]"
        $message = $e->getMessage();
        if (preg_match('/\[([\d,]+)\]$/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get friendly HTTP exception message
     */
    protected function getHttpExceptionMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Yêu cầu không hợp lệ.',
            401 => 'Vui lòng đăng nhập để tiếp tục.',
            403 => 'Bạn không có quyền thực hiện hành động này.',
            404 => 'Không tìm thấy tài nguyên này.',
            405 => 'Phương thức không được hỗ trợ.',
            422 => 'Dữ liệu không hợp lệ.',
            429 => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
            500 => 'Đã xảy ra lỗi server. Vui lòng thử lại sau.',
            503 => 'Dịch vụ tạm thời không khả dụng.',
        ];

        return $messages[$statusCode] ?? 'Đã xảy ra lỗi.';
    }
}
