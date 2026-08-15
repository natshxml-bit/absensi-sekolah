<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('OK', 200)->header('X-Healthcheck', 'true');
});

Route::get('/fix-admin', function () {
    $user = \App\Models\User::where('email', 'admin@sekolah.sch.id')->first();
    if ($user) {
        $user->update(['name' => 'Admin79']);
        return 'Admin name updated to Admin79';
    }
    return 'Admin not found';
});
