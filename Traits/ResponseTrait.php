<?php

namespace App\Traits;


use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

trait ResponseTrait
{
    protected function returnDataWithPagination($message, $statusCode, $data)
    {
        return Response::json([
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $data->resolve(),
            'pagination' => $data->additional['pagination'] ?? null,
        ], $statusCode);
    }

    public function returnError($msg, $code): void
    {
        abort($code, $msg);
    }
    public function success($msg,$code): JsonResponse
    {
        return Response::json([
            'status' => 'success',
            'code' => $code,
            'message' => $msg,
        ]);
    }
    public function returnData($msg, $code, $value): JsonResponse
    {
        return Response::json([
            'status' => 'success',
            'code' => $code,
            'message' => $msg,
            'data' => $value,
        ]);
    }
}
