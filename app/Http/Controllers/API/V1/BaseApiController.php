<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseApiController extends Controller
{
    protected function successResponse(array $data, int $status = 200): JsonResponse
    {
        if (!isset($data['message'])) {
            $data['message'] = 'Data retrieved successfully';
        }
        return response()->json(['success' => true, ...$data], $status);
    }

    protected function errorResponse(array $data, int|string $status): JsonResponse
    {
        $status = is_numeric($status) && $status >= 300 && $status <= 599 ? $status : 500;
        return response()->json(['success' => false, ...$data], $status);
    }

    protected function handleServiceCall(callable $callback, string $message = 'Data retrieved successfully'): JsonResponse
    {
        try {
            $result = $callback();

            return $this->successResponse([
                'message' => $message,
                ...(is_array($result) ? ['data' => $result] : [])
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() > 300 && $e->getCode() <= 500 ? $e->getCode() : 500;

            return $this->errorResponse([
                'message' => $e->getMessage(),
            ], $code);
        }
    }
}
