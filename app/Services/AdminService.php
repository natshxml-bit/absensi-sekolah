<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Guardian;
use App\Models\Major;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(private readonly ActivityLogService $logs)
    {
    }

    public function overview(): array
    {
        $today = now()->toDateString();

        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalClasses = ClassRoom::count();

        $todayCounts = Attendance::query()
            ->where('date', $today)
            ->get()
            ->groupBy('status')
            ->map->count();

        $recordedToday = (int) $todayCounts->sum();

        return [
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'total_classes' => $totalClasses,
            'today' => [
                'date' => $today,
                'hadir' => (int) ($todayCounts['hadir'] ?? 0),
                'terlambat' => (int) ($todayCounts['terlambat'] ?? 0),
                'izin' => (int) ($todayCounts['izin'] ?? 0),
                'sakit' => (int) ($todayCounts['sakit'] ?? 0),
                'alfa' => (int) ($todayCounts['alfa'] ?? 0),
                'belum_absen' => max(0, $totalStudents - $recordedToday),
            ],
        ];
    }

    public function createClass(string $name, string $majorName): ClassRoom
    {
        $major = Major::firstOrCreate(['name' => $majorName]);

        $class = ClassRoom::create(['name' => $name, 'major_id' => $major->id]);

        $this->logs->record(auth()->user(), 'class.create', "Tambah kelas {$name} (jurusan {$majorName}).", $class);

        return $class->load('major');
    }

    public function createStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_SISWA,
                'phone' => $data['phone'] ?? null,
            ]);

            $guardian = null;
            if (! empty($data['parent_email'])) {
                $guardian = Guardian::firstOrCreate(
                    ['user_id' => $this->ensureGuardianUser($data)->id],
                    ['phone' => $data['parent_phone'] ?? null],
                );
            }

            $student = Student::create([
                'user_id' => $user->id,
                'nis' => $data['nis'],
                'class_id' => $data['class_id'],
                'parent_id' => $guardian?->id,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->logs->record(
                auth()->user(),
                'student.create',
                "Tambah siswa {$user->name} (NIS {$data['nis']}).",
                $student,
            );

            return $student;
        });
    }

    private function ensureGuardianUser(array $data): User
    {
        $user = User::where('email', $data['parent_email'])->first();

        if ($user !== null) {
            return $user;
        }

        return User::create([
            'name' => $data['parent_name'] ?? ($data['name'].' (Wali)'),
            'email' => $data['parent_email'],
            'password' => $data['parent_password'] ?? $data['password'],
            'role' => User::ROLE_ORTU,
            'phone' => $data['parent_phone'] ?? null,
        ]);
    }

    public const IMPORT_DEFAULT_PASSWORD = 'siswa123';

    /**
     * Mengimpor data siswa dari file xlsx rekap absensi.
     * Setiap sheet = satu kelas; NIS & email dibuatkan otomatis.
     *
     * @return array{classes_created: int, students_imported: int, skipped: int, failed: array<int, array{row: int, name: string, reason: string}>}
     */
    public function logClearAttendance(int $deleted): void
    {
        $this->logs->record(
            auth()->user(),
            'attendance.clear',
            "Semua catatan absensi dihapus ({$deleted} record)."
        );
    }

    public function importStudents(string $filePath): array
    {
        $groups = app(XlsxReaderService::class)->readStudentRecap($filePath);

        $summary = [
            'classes_created' => 0,
            'students_imported' => 0,
            'skipped' => 0,
            'failed' => [],
        ];

        if (empty($groups)) {
            throw new \InvalidArgumentException('Tidak ada data siswa yang ditemukan di file excel ini.');
        }

        DB::transaction(function () use ($groups, &$summary) {
            $seq = (int) (Student::orderByDesc('id')->value('id') ?? 0);

            foreach ($groups as $group) {
                $class = ClassRoom::where('name', $group['class'])->first();

                if ($class === null) {
                    $major = Major::firstOrCreate(['name' => 'Umum']);
                    $class = ClassRoom::create(['name' => $group['class'], 'major_id' => $major->id]);
                    $summary['classes_created']++;
                }

                $code = $this->classCode($group['class']);

                foreach ($group['students'] as $index => $row) {
                    $name = mb_substr(trim($row['name']), 0, 255);
                    $seq++;
                    $nis = strtoupper($code).'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                    $email = strtolower($nis).'@siswa.sch.id';

                    try {
                        $duplicate = Student::query()
                            ->where('class_id', $class->id)
                            ->whereHas('user', fn (Builder $q) => $q->where('name', $name))
                            ->exists();

                        if ($duplicate) {
                            $summary['skipped']++;
                            continue;
                        }

                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => self::IMPORT_DEFAULT_PASSWORD,
                            'role' => User::ROLE_SISWA,
                        ]);

                        Student::create([
                            'user_id' => $user->id,
                            'nis' => $nis,
                            'class_id' => $class->id,
                            'gender' => $row['gender'],
                        ]);

                        $summary['students_imported']++;
                    } catch (\Throwable $e) {
                        $summary['failed'][] = [
                            'row' => $index + 1,
                            'name' => $name,
                            'reason' => $e->getMessage(),
                        ];
                    }
                }
            }
        });

        $this->logs->record(
            auth()->user(),
            'student.import',
            'Import data siswa dari excel: '.$summary['students_imported'].' siswa, '
                .$summary['classes_created'].' kelas baru, '
                .$summary['skipped'].' dilewati.'
        );

        return $summary;
    }

    private function classCode(string $className): string
    {
        $code = strtoupper(preg_replace('/[^a-z0-9]/i', '', $className));

        return mb_substr($code, 0, 6) ?: 'KLS';
    }

    public function createTeacher(array $data): Teacher
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_GURU,
                'phone' => $data['phone'] ?? null,
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'nip' => $data['nip'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->logs->record(
                auth()->user(),
                'teacher.create',
                "Tambah guru {$user->name}.".($data['nip'] ?? '' ? " NIP {$data['nip']}." : ''),
                $teacher,
            );

            return $teacher;
        });
    }

    public function createSchedule(array $data): Schedule
    {
        $schedule = Schedule::create($data);

        $this->logs->record(
            auth()->user(),
            'schedule.create',
            "Tambah jadwal {$data['subject']} (kelas {$schedule->classRoom->name}, {$data['day']}).",
            $schedule,
        );

        return $schedule;
    }

    public function updateSettings(array $values): array
    {
        $validKeys = [
            Setting::KEY_SCHOOL_NAME,
            Setting::KEY_LATITUDE,
            Setting::KEY_LONGITUDE,
            Setting::KEY_RADIUS_METERS,
            Setting::KEY_LATE_TIME,
        ];

        foreach ($values as $key => $value) {
            if (in_array($key, $validKeys, true)) {
                Setting::set($key, $value);
            }
        }

        $this->logs->record(
            auth()->user(),
            'settings.update',
            'Pengaturan sekolah diperbarui.',
        );

        return Setting::allValues();
    }

    public function attendanceIndex(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Attendance::query()
            ->with(['student.user', 'student.classRoom'])
            ->when(
                ! empty($filters['date']),
                fn (Builder $q) => $q->whereDate('date', $filters['date']),
            )
            ->when(
                ! empty($filters['class_id']),
                fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('class_id', $filters['class_id'])),
            )
            ->when(
                ! empty($filters['student_id']),
                fn (Builder $q) => $q->where('student_id', $filters['student_id']),
            )
            ->when(
                ! empty($filters['status']),
                fn (Builder $q) => $q->where('status', $filters['status']),
            )
            ->latest('date')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}