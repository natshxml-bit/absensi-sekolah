<?php

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;

class TeacherService
{
    public function myClasses(Teacher $teacher): \Illuminate\Support\Collection
    {
        return $teacher->schedules()
            ->with('classRoom.major')
            ->get()
            ->groupBy('class_id')
            ->map(function ($schedules, $classId) {
                $class = $schedules->first()->classRoom;

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'major' => $class->major?->name,
                    'subjects' => $schedules->pluck('subject')->unique()->values(),
                ];
            })
            ->values();
    }

    public function classStudents(Teacher $teacher, int $classId): \Illuminate\Support\Collection
    {
        $this->ensureOwnsClass($teacher, $classId);

        return Student::with('user', 'classRoom')
            ->where('class_id', $classId)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->user->name,
                'nis' => $student->nis,
                'gender' => $student->gender,
            ]);
    }

    public function classAttendance(Teacher $teacher, int $classId, ?string $date): \Illuminate\Support\Collection
    {
        $this->ensureOwnsClass($teacher, $classId);

        $date ??= now()->toDateString();

        return Student::with('user')
            ->where('class_id', $classId)
            ->get()
            ->map(function (Student $student) use ($date) {
                $attendance = $student->attendance()->where('date', $date)->first();

                return [
                    'student_id' => $student->id,
                    'name' => $student->user->name,
                    'nis' => $student->nis,
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'status' => $attendance->status,
                        'status_label' => Attendance::statusLabel($attendance->status),
                        'check_in_time' => $attendance->check_in_time,
                        'photo_url' => $attendance->photo_url,
                        'notes' => $attendance->notes,
                    ] : null,
                ];
            });
    }

    public function storeAttendance(Teacher $teacher, int $classId, int $studentId, string $status, ?string $notes): Attendance
    {
        $this->ensureOwnsClass($teacher, $classId);

        $student = Student::where('id', $studentId)->where('class_id', $classId)->firstOrFail();

        $today = now()->toDateString();

        $attendance = $student->attendance()->where('date', $today)->first();

        if ($attendance) {
            $attendance->update([
                'status' => $status,
                'notes' => $notes,
                'check_in_time' => $status !== 'alfa' ? now()->format('H:i:s') : null,
            ]);
        } else {
            $attendance = $student->attendance()->create([
                'date' => $today,
                'status' => $status,
                'notes' => $notes,
                'check_in_time' => $status !== 'alfa' ? now()->format('H:i:s') : null,
            ]);
        }

        return $attendance;
    }

    public function exportAttendance(Teacher $teacher, int $classId, ?string $from, ?string $to): string
    {
        $this->ensureOwnsClass($teacher, $classId);

        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();

        $students = Student::with('user')->where('class_id', $classId)->get();

        $csv = "NIS,Nama,Tanggal,Status,Waktu Masuk,Catatan\n";

        foreach ($students as $student) {
            $attendances = $student->attendance()
                ->whereBetween('date', [$from, $to])
                ->orderBy('date')
                ->get();

            foreach ($attendances as $a) {
                $csv .= "\"{$student->nis}\",\"{$student->user->name}\",\"{$a->date}\",\"{$a->status}\",\"{$a->check_in_time}\",\"{$a->notes}\"\n";
            }
        }

        return $csv;
    }

    private function ensureOwnsClass(Teacher $teacher, int $classId): void
    {
        $owns = $teacher->schedules()->where('class_id', $classId)->exists();

        if (! $owns) {
            throw new AuthorizationException('Anda tidak mengajar di kelas ini.');
        }
    }
}