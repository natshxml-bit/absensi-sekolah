<?php

namespace App\Services\PhotoStorage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LocalPhotoStorage implements PhotoStorage
{
    public function store(UploadedFile|string $file, array $meta): string
    {
        $date = $meta['date'] ?? now()->toDateString();
        $prefix = $meta['prefix'] ?? 'attendance';
        $filename = $meta['filename'] ?? bin2hex(random_bytes(6));

        $extension = $file instanceof UploadedFile
            ? $file->getClientOriginalExtension() ?: 'jpg'
            : 'jpg';

        $path = "{$prefix}/{$date}/{$filename}.{$extension}";

        Storage::disk('public')->putFileAs(
            "{$prefix}/{$date}",
            $file,
            "{$filename}.{$extension}",
        );

        return $path;
    }

    public function delete(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    public function url(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return Storage::disk('public')->url($path);
    }
}