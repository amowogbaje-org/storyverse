<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'Storyverse API',
        'status' => 'ok',
        'docs' => '/api/v1',
    ]);
});
