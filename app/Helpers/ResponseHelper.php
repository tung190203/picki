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
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $code, [], self::jsonOptions());
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
