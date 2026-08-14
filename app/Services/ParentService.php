<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;

class ParentService
{
    public function children(Guardian $guardian): \Illuminate\Support\Collection
    {
        return $guardian->students()
            ->with('user', 'classRoom.major')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->user->name,
                'nis' => $student->nis,
                'class' => $student->classRoom ? [
                    'name' => $student->classRoom->name,
                    'major' => $student->classRoom->major?->name,
                ] : null,
            ]);
    }

    public function childAttendance(Student $student, ?string $month): \Illuminate\Support\Collection
    {
        $query = $student->attendance();

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $query->whereYear('date', substr($month, 0, 4))
                ->whereMonth('date', substr($month, 5, 2));
        }

        return $query->latest('date')
            ->get()
            ->map(fn ($attendance) => [
                'date' => $attendance->date->format('Y-m-d'),
                'check_in_time' => $attendance->check_in_time,
                'status' => $attendance->status,
                'status_label' => \App\Models\Attendance::statusLabel($attendance->status),
                'notes' => $attendance->notes,
                'photo_url' => $attendance->photo_url,
            ]);
    }
}
