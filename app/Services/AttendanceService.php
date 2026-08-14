<?php

namespace App\Services;

use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\OutsideRadiusException;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Student;
use App\Services\PhotoStorage\PhotoStorage;
use Illuminate\Http\UploadedFile;

class AttendanceService
{
    public const EARTH_RADIUS_METERS = 6371000;

    public function __construct(
        private readonly PhotoStorage $photos,
        private readonly ActivityLogService $logs,
    ) {}

    /**
     * Absen masuk (selfie + GPS) oleh siswa.
     * Waktu dicatat dari server (Asia/Jakarta), bukan device.
     *
     * @throws OutsideRadiusException
     * @throws AlreadyCheckedInException
     */
    public function checkIn(
        Student $student,
        float $latitude,
        float $longitude,
        UploadedFile $photo,
        ?string $deviceInfo = null,
    ): Attendance {
        $today = now()->toDateString();

        if ($student->attendance()->where('date', $today)->exists()) {
            throw new AlreadyCheckedInException();
        }

        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            (float) Setting::get(Setting::KEY_LATITUDE, 0),
            (float) Setting::get(Setting::KEY_LONGITUDE, 0),
        );

        $radius = (float) Setting::get(Setting::KEY_RADIUS_METERS, 100);

        if ($distance > $radius) {
            throw new OutsideRadiusException(
                "Anda berada di luar radius absensi sekolah (jarak {$this->formatDistance($distance)}).",
            );
        }

        $now = now();
        $status = $this->resolveStatus($now);

        $path = $this->photos->store($photo, [
            'date' => $today,
            'prefix' => 'attendance',
            'filename' => $student->nis.'_'.$now->format('His'),
        ]);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => $today,
            'check_in_time' => $now->format('H:i:s'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'photo' => $path,
            'status' => $status,
            'device_info' => $deviceInfo,
        ]);

        $this->logs->record(
            $student->user,
            'checkin',
            "Absen {$status}: {$student->user->name} (NIS {$student->nis}), jarak {$this->formatDistance($distance)} dari sekolah.",
            $attendance,
        );

        return $attendance;
    }

    /**
     * Catat manual oleh admin: izin / sakit / alfa.
     * Foto opsional (mis. bukti surat izin).
     *
     * @throws AlreadyCheckedInException
     */
    public function recordManual(
        Student $student,
        string $date,
        string $status,
        ?UploadedFile $photo = null,
        ?string $notes = null,
    ): Attendance {
        if ($student->attendance()->where('date', $date)->exists()) {
            throw new AlreadyCheckedInException('Siswa sudah memiliki catatan absensi pada tanggal tersebut.');
        }

        $path = null;
        if ($photo !== null) {
            $path = $this->photos->store($photo, [
                'date' => $date,
                'prefix' => 'attendance',
                'filename' => $student->nis.'_manual',
            ]);
        }

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => $date,
            'status' => $status,
            'photo' => $path,
            'notes' => $notes,
        ]);

        $this->logs->record(
            auth()->user(),
            'attendance.manual',
            "Catatan manual {$status}: {$student->user->name} (NIS {$student->nis}) tanggal {$date}.",
            $attendance,
        );

        return $attendance;
    }

    private function resolveStatus(\Illuminate\Support\Carbon $now): string
    {
        $lateTime = (string) Setting::get(Setting::KEY_LATE_TIME, '07:30');

        return $now->format('H:i') > $lateTime
            ? Attendance::STATUS_TERLAMBAT
            : Attendance::STATUS_HADIR;
    }

    /**
     * Jarak haversine dalam meter.
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function formatDistance(float $meters): string
    {
        return $meters >= 1000
            ? number_format($meters / 1000, 2).' km'
            : number_format($meters, 0).' m';
    }
}
