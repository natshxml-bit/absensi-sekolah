<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Services\TeacherService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(private readonly TeacherService $service)
    {
    }

    public function classes(Request $request)
    {
        return response()->json(['data' => $this->service->myClasses($request->user()->teacher)]);
    }

    public function classStudents(Request $request, int $class)
    {
        try {
            $students = $this->service->classStudents($request->user()->teacher, $class);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['data' => $students]);
    }

    public function attendance(Request $request, int $class)
    {
        try {
            $attendance = $this->service->classAttendance(
                $request->user()->teacher,
                $class,
                $request->date,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => [
                'date' => $request->date ?? now()->toDateString(),
                'students' => $attendance,
            ],
        ]);
    }

    public function storeAttendance(Request $request, int $class)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'status' => 'required|in:hadir,terlambat,izin,sakit,alfa',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $attendance = $this->service->storeAttendance(
                $request->user()->teacher,
                $class,
                $validated['student_id'],
                $validated['status'],
                $validated['notes'] ?? null,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['data' => $attendance, 'message' => 'Absensi berhasil disimpan.']);
    }

    public function exportAttendance(Request $request, int $class)
    {
        try {
            $csv = $this->service->exportAttendance(
                $request->user()->teacher,
                $class,
                $request->from,
                $request->to,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $filename = "absensi-kelas-{$class}-" . now()->format('Y-m-d') . ".csv";

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}