<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('OK', 200)->header('X-Healthcheck', 'true');
});

Route::get('/callback', function (Illuminate\Http\Request $request) {
    return response()->json($request->only('code', 'error'));
});

Route::get('/seed/{secret}', function ($secret) {
    if ($secret !== 'absensi79seed') {
        abort(403);
    }
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true, '--class' => 'FullDatabaseSeeder']);
    return response(\Illuminate\Support\Facades\Artisan::output());
});
