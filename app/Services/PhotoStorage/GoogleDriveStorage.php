<?php

namespace App\Services\PhotoStorage;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveStorage implements PhotoStorage
{
    private Client $client;
    private string $folderId;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google_drive.client_id', ''));
        $this->client->setClientSecret(config('services.google_drive.client_secret', ''));
        $this->client->refreshToken(config('services.google_drive.refresh_token', ''));
        $this->client->addScope(Drive::DRIVE_FILE);
        $this->folderId = config('services.google_drive.folder_id', '');
    }

    public function store(UploadedFile|string $file, array $meta): string
    {
        $date = $meta['date'] ?? now()->toDateString();
        $prefix = $meta['prefix'] ?? 'attendance';
        $filename = $meta['filename'] ?? bin2hex(random_bytes(6));

        if ($file instanceof UploadedFile) {
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $tmpPath = $file->getPathname();
            $mimeType = $file->getMimeType() ?: 'image/jpeg';
        } else {
            $extension = 'jpg';
            $mimeType = 'image/jpeg';
            $tmpPath = tempnam(sys_get_temp_dir(), 'gdrive_');
            file_put_contents($tmpPath, $file);
        }

        $driveFilename = "{$prefix}/{$date}/{$filename}.{$extension}";

        try {
            $service = new Drive($this->client);

            $fileMetadata = new Drive\DriveFile([
                'name' => $driveFilename,
                'parents' => [$this->folderId],
            ]);

            $content = file_get_contents($tmpPath);
            $uploaded = $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
            ]);

            $permission = new Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);
            $service->permissions->create($uploaded->getId(), $permission);

            if (is_string($file) || $file instanceof UploadedFile) {
                @unlink($tmpPath);
            }

            return $uploaded->getId();
        } catch (\Exception $e) {
            Log::error('Google Drive upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        try {
            $service = new Drive($this->client);
            $service->files->delete($path);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive delete failed: ' . $e->getMessage());
            return false;
        }
    }

    public function url(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return "https://drive.google.com/uc?export=view&id={$path}";
    }
}
