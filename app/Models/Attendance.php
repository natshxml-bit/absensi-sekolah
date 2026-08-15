<?php

namespace App\Models;

use App\Services\PhotoStorage\PhotoStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    public const STATUS_HADIR = 'hadir';
    public const STATUS_TERLAMBAT = 'terlambat';
    public const STATUS_IZIN = 'izin';
    public const STATUS_SAKIT = 'sakit';
    public const STATUS_ALFA = 'alfa';

    public const STATUSES = [
        self::STATUS_HADIR,
        self::STATUS_TERLAMBAT,
        self::STATUS_IZIN,
        self::STATUS_SAKIT,
        self::STATUS_ALFA,
    ];

    public const STATUS_LABELS = [
        self::STATUS_HADIR => 'Hadir',
        self::STATUS_TERLAMBAT => 'Terlambat',
        self::STATUS_IZIN => 'Izin',
        self::STATUS_SAKIT => 'Sakit',
        self::STATUS_ALFA => 'Alfa',
    ];

    public const MANUAL_STATUSES = [
        self::STATUS_IZIN,
        self::STATUS_SAKIT,
        self::STATUS_ALFA,
    ];

    protected $table = 'attendance';

    protected $fillable = [
        'student_id',
        'date',
        'check_in_time',
        'latitude',
        'longitude',
        'photo',
        'status',
        'device_info',
        'notes',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo === null || $this->photo === '') {
            return '';
        }

        return app(PhotoStorage::class)->url($this->photo);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}