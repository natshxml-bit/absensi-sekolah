<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\ManualAttendanceRequest;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Services\AdminService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $service,
        private readonly AttendanceService $attendanceService,
    ) {
    }

    public function overview()
    {
        return response()->json($this->service->overview());
    }

    public function classes()
    {
        $classes = \App\Models\ClassRoom::with('major', 'students')->orderBy('name')->get()->map(
            fn ($class) => [
                'id' => $class->id,
                'name' => $class->name,
                'major' => $class->major?->name,
                'student_count' => $class->students_count ?? $class->students->count(),
            ],
        );

        return response()->json(['data' => $classes]);
    }

    public function storeClass(StoreClassRequest $request)
    {
        $class = $this->service->createClass($request->name, $request->major_name);

        return response()->json(['message' => 'Kelas berhasil ditambahkan.', 'data' => $class], 201);
    }

    public function students()
    {
        $students = Student::with('user', 'classRoom.major')->orderBy('nis')->get()->map(
            fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'nis' => $student->nis,
                'gender' => $student->gender,
                'phone' => $student->phone,
                'address' => $student->address,
                'class' => $student->classRoom ? [
                    'id' => $student->classRoom->id,
                    'name' => $student->classRoom->name,
                    'major' => $student->classRoom->major?->name,
                ] : null,
            ],
        );

        return response()->json(['data' => $students]);
    }

    public function storeStudent(StoreStudentRequest $request)
    {
        $student = $this->service->createStudent($request->validated());

        return response()->json([
            'message' => 'Siswa berhasil ditambahkan.',
            'data' => ['id' => $student->id, 'nis' => $student->nis],
        ], 201);
    }

    public function importStudents(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'file.required' => 'File excel wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $summary = $this->service->importStudents($request->file('file')->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Import selesai: '.$summary['students_imported'].' siswa berhasil diimpor.',
            'data' => $summary,
        ], 201);
    }

    public function teachers()
    {
        $teachers = \App\Models\Teacher::with('user')->orderBy('user_id')->get()->map(
            fn ($teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->user->name,
                'email' => $teacher->user->email,
                'nip' => $teacher->nip,
                'gender' => $teacher->gender,
                'phone' => $teacher->phone,
                'address' => $teacher->address,
            ],
        );

        return response()->json(['data' => $teachers]);
    }

    public function storeTeacher(StoreTeacherRequest $request)
    {
        $teacher = $this->service->createTeacher($request->validated());

        return response()->json([
            'message' => 'Guru berhasil ditambahkan.',
            'data' => ['id' => $teacher->id],
        ], 201);
    }

    public function schedules()
    {
        $schedules = \App\Models\Schedule::with('classRoom.major', 'teacher.user')
            ->orderBy('day')->orderBy('start_time')
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'class' => [
                    'id' => $schedule->classRoom->id,
                    'name' => $schedule->classRoom->name,
                    'major' => $schedule->classRoom->major?->name,
                ],
                'teacher' => $schedule->teacher->user->name,
                'subject' => $schedule->subject,
                'day' => $schedule->day,
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
            ]);

        return response()->json(['data' => $schedules]);
    }

    public function storeSchedule(StoreScheduleRequest $request)
    {
        $schedule = $this->service->createSchedule($request->validated());

        return response()->json(['message' => 'Jadwal berhasil ditambahkan.', 'data' => ['id' => $schedule->id]], 201);
    }

    public function settings()
    {
        return response()->json(['data' => Setting::allValues()]);
    }

    public function updateSettings(UpdateSettingsRequest $request)
    {
        $values = $this->service->updateSettings($request->validated());

        return response()->json(['message' => 'Pengaturan berhasil disimpan.', 'data' => $values]);
    }

    public function attendance(AttendanceIndexRequest $request)
    {
        $result = $this->service->attendanceIndex($request->validated());

        $result->getCollection()->transform(function ($attendance) {
            return [
                'id' => $attendance->id,
                'student' => $attendance->student ? [
                    'id' => $attendance->student->id,
                    'name' => $attendance->student->user->name,
                    'nis' => $attendance->student->nis,
                    'class' => $attendance->student->classRoom?->name,
                ] : null,
                'date' => $attendance->date->format('Y-m-d'),
                'check_in_time' => $attendance->check_in_time,
                'latitude' => $attendance->latitude,
                'longitude' => $attendance->longitude,
                'photo_url' => $attendance->photo_url,
                'status' => $attendance->status,
                'status_label' => \App\Models\Attendance::statusLabel($attendance->status),
                'device_info' => $attendance->device_info,
                'notes' => $attendance->notes,
            ];
        });

        return response()->json($result);
    }

    public function clearAttendance(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))));

        if (empty($ids)) {
            return response()->json(['message' => 'Pilih minimal satu catatan absensi terlebih dahulu.'], 422);
        }

        $deleted = \App\Models\Attendance::query()->whereIn('id', $ids)->delete();

        $this->service->logClearAttendance($deleted);

        return response()->json(['message' => $deleted.' catatan absensi dihapus.']);
    }

    public function storeManualAttendance(ManualAttendanceRequest $request)
    {
        $student = Student::findOrFail($request->student_id);

        $attendance = $this->attendanceService->recordManual(
            $student,
            $request->date,
            $request->status,
            $request->file('photo'),
            $request->notes,
        );

        return response()->json([
            'message' => 'Catatan absensi berhasil disimpan.',
            'data' => ['id' => $attendance->id, 'status' => $attendance->status],
        ], 201);
    }
}