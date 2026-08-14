<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public const KEY_SCHOOL_NAME = 'school_name';
    public const KEY_LATITUDE = 'latitude';
    public const KEY_LONGITUDE = 'longitude';
    public const KEY_RADIUS_METERS = 'radius_meters';
    public const KEY_LATE_TIME = 'late_time';

    public const DEFAULTS = [
        self::KEY_SCHOOL_NAME => '',
        self::KEY_LATITUDE => '0',
        self::KEY_LONGITUDE => '0',
        self::KEY_RADIUS_METERS => '100',
        self::KEY_LATE_TIME => '07:30',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function allValues(): array
    {
        $values = static::pluck('value', 'key')->all();

        return array_merge(static::DEFAULTS, $values);
    }
}