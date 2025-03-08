<?php

namespace App\Traits;


use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

trait ResponseTrait
{
    protected function returnDataWithPagination($message, $statusCode, $data)
    {
        // Check if the data is an AnonymousResourceCollection and has an underlying paginator
        $pagination = null;
        if ($data instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection) {
            $resource = $data->resource;
            if ($resource instanceof \Illuminate\Pagination\AbstractPaginator) {
                $pagination = [
                    'total' => $resource->total(),
                    'per_page' => $resource->perPage(),
                    'current_page' => $resource->currentPage(),
                    'last_page' => $resource->lastPage(),
                    'from' => $resource->firstItem(),
                    'to' => $resource->lastItem(),
                ];
            }
        }

        return Response::json([
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $data->resolve(),
            'pagination' => $pagination,
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
