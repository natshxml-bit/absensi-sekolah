<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('OK', 200)->header('X-Healthcheck', 'true');
});

Route::get('/callback', function (Illuminate\Http\Request $request) {
    return response()->json($request->only('code', 'error'));
});
