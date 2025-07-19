<?php

function success($data, $message = "success")
{
    return response()->json([
        'status' => 1,
        'result' => 1,
        'message' => $message,
        'data' => $data,
    ]);
}

function fail($message = "fail", $data = [], $target = "")
{
    return response()->json([
        'status' => 0,
        'result' => 0,
        'message' => $message,
        'target' => $target,
        'data' => $data,
    ]);
}

function successMessage($message = "success")
{
    return response()->json([
        'status' => 1,
        'result' => 1,
        'message' => $message,
    ]);
}

function failedMessage($message = "fail")
{
    return response()->json([
        'status' => 0,
        'result' => 0,
        'message' => $message,
        'data' => null
    ]);
}


function notFoundMessage($message = "Your item cannot be found.")
{
    return response()->json([
        'status' => 0,
        'result' => 0,
        'message' => $message,
        'data' => null
    ]);
}
