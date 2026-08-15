<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('OK', 200)->header('X-Healthcheck', 'true');
});

Route::get('/seed/{secret}', function ($secret) {
    if ($secret !== 'absensi79seed') {
        abort(403);
    }
    \Illuminate\Support\Facades\Artisan::call('import:students');
    return response(\Illuminate\Support\Facades\Artisan::output());
});
