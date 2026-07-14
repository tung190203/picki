<?php

namespace App\Helpers;

class ResponseHelper
{
    private static function jsonOptions(): int
    {
        $options = JSON_UNESCAPED_UNICODE;
        if (config('app.debug')) {
            $options |= JSON_PRETTY_PRINT;
        }
        return $options;
    }

    /**
     * Success Response
     */
    public static function success($data = [], $message = 'Success', $code = 200, $meta = null)
    {
        \Log::info("ResponseHelper::success - START");

        $payload = [
            'status' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ];

        \Log::info("ResponseHelper::success - payload built", [
            'has_data' => !empty($data),
            'data_keys' => is_array($data) ? array_keys($data) : 'non-array',
        ]);

        $response = response()->json($payload, $code, [], self::jsonOptions());

        \Log::info("ResponseHelper::success - json() called");

        $content = $response->getContent();

        \Log::info("ResponseHelper::success - getContent() called", [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200),
        ]);

        return $response;
    }

    /**
     * Success Response (Single Item)
     */
    public static function single($data = [], $message = 'Success', $meta = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta ?? new \stdClass(),
            'error' => null,
        ], $code, [], self::jsonOptions());
    }

    /**
     * Success Response (Paginated)
     */
    public static function paginated($data = [], $meta = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'message' => $message,
            ], $meta),
            'error' => null,
        ], $code, [], self::jsonOptions());
    }

    /**
     * Error Response
     */
    public static function error($message = 'Error', $code = 400, $errors = [])
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code, [], self::jsonOptions());
    }
}
