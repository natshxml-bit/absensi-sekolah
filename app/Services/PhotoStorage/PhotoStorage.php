<?php

namespace App\Services\PhotoStorage;

use Illuminate\Http\UploadedFile;

/**
 * Contract penyimpanan foto absensi.
 *
 * Implementasi saat ini: LocalPhotoStorage (disk public).
 * Untuk migrasi ke Google Drive / Cloud Storage,
 * cukup buat implementasi baru dari interface ini
 * dan ubah binding di AppServiceProvider.
 */
interface PhotoStorage
{
    /**
     * Simpan file foto, kembalikan path relatif terhadap storage.
     */
    public function store(UploadedFile|string $file, array $meta): string;

    /**
     * Hapus foto berdasarkan path relatif.
     */
    public function delete(string $path): bool;

    /**
     * URL publik untuk path relatif.
     */
    public function url(string $path): string;
}