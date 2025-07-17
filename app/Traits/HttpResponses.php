<?php

namespace App\Traits;

trait HttpResponses
{
    protected function success($data, $message = null, $code = 200)
    {
        return response()->json([
            'status' => 1,
            'message' => $message ?? 'Success',
            'result' => $data
        ], $code);
    }

    protected function error($data, $message = null, $code = 500)
    {
        return response()->json([
            'status' => 0,
            'message' => $message ?? 'Error has occurred.',
            'result' => $data
        ], $code);
    }
}
