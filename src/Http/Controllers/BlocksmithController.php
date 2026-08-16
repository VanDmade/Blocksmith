<?php

namespace VanDmade\Blocksmith\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class BlocksmithController extends BaseController
{

    public function success($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    public function error(string $message, int $status = 500): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

}
