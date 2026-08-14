<?php

namespace App\Services;

use App\Models\Student;

class StudentService
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function profile(Student $student): array
    {
        return [
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
            'parent' => $student->guardian?->user?->name,
        ];
    }

    public function todayStatus(Student $student): ?array
    {
        $attendance = $student->attendance()
            ->where('date', now()->toDateString())
            ->first();

        if ($attendance === null) {
            return null;
        }

        return $this->present($attendance);
    }

    public function history(Student $student, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $student->attendance()
            ->latest('date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function present(\App\Models\Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'date' => $attendance->date->format('Y-m-d'),
            'check_in_time' => $attendance->check_in_time,
            'latitude' => $attendance->latitude,
            'longitude' => $attendance->longitude,
            'photo' => $attendance->photo,
            'photo_url' => $attendance->photo_url,
            'status' => $attendance->status,
            'status_label' => \App\Models\Attendance::statusLabel($attendance->status),
            'notes' => $attendance->notes,
        ];
    }

    public function schedules(Student $student): \Illuminate\Support\Collection
    {
        if (!$student->class_id) {
            return collect();
        }

        return \App\Models\Schedule::with('teacher.user')
            ->where('class_id', $student->class_id)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'subject' => $s->subject,
                'day' => $s->day,
                'start_time' => $s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : $s->start_time,
                'end_time' => $s->end_time instanceof \Carbon\Carbon ? $s->end_time->format('H:i') : $s->end_time,
                'teacher' => $s->teacher?->user?->name,
            ]);
    }
}