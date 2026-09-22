<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Real per-visitor session id now (this app has actual sessions), so
     * anonymous view/analytics dedup doesn't need the old X-Session-Id
     * header hack the JWT-only API used.
     */
    protected function sessionHash(Request $request): string
    {
        return substr($request->session()->getId(), 0, 64);
    }
}
