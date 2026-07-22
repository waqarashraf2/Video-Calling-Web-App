<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class CsrfTokenController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }
}
