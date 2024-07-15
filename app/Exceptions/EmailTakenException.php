<?php

namespace App\Exceptions;

use Exception;

class EmailTakenException extends Exception
{
    public function render($request)
    {
        return response()->view('oauth.emailTaken', [], 400);
    }
}
