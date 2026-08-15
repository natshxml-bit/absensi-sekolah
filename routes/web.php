<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('OK', 200)->header('X-Healthcheck', 'true');
});

Route::get('/photo/{fileId}', function ($fileId) {
    $url = "https://lh3.googleusercontent.com/d/{$fileId}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $image = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($status !== 200 || !$image) {
        return response('Not found', 404);
    }

    return response($image, 200)
        ->header('Content-Type', $type ?: 'image/jpeg')
        ->header('Cache-Control', 'public, max-age=86400');
});

Route::get('/debug-photo/{id}', function ($id) {
    $a = \App\Models\Attendance::find($id);
    if (!$a) return 'not found';
    $student = \App\Models\Student::find($a->student_id);
    return response()->json([
        'id' => $a->id,
        'student_id' => $a->student_id,
        'student_name' => $student?->user?->name ?? 'null',
        'photo' => $a->photo,
        'photo_url' => $a->photo_url,
    ]);
});
