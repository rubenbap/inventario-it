<?php

namespace App\Http\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $payload = null, string|array $messages = '', int $code = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'messages' => $messages, 'payload' => $payload], $code);
    }

    public static function error(string|array $messages, int $code = 200, mixed $payload = null): JsonResponse
    {
        return response()->json(['status' => 'error', 'messages' => $messages, 'payload' => $payload], $code);
    }

    public static function rows(int $total, array $rows): JsonResponse
    {
        return response()->json(['total' => $total, 'rows' => $rows]);
    }
}
