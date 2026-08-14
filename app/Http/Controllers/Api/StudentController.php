<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\OutsideRadiusException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Services\AttendanceService;
use App\Services\StudentService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $service,
        private readonly AttendanceService $attendanceService,
    ) {
    }

    public function profile(Request $request)
    {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $this->service->profile($student)]);
    }

    public function today(Request $request)
    {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => [
                'date' => now()->toDateString(),
                'server_time' => now()->format('H:i:s'),
                'attendance' => $this->service->todayStatus($student),
            ],
        ]);
    }

    public function checkIn(CheckInRequest $request)
    {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        try {
            $attendance = $this->attendanceService->checkIn(
                $student,
                (float) $request->latitude,
                (float) $request->longitude,
                $request->file('photo'),
                $request->device_info,
            );
        } catch (AlreadyCheckedInException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (OutsideRadiusException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Absensi berhasil dicatat.',
            'data' => $this->service->present($attendance),
        ], 201);
    }

    public function history(Request $request)
    {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $history = $this->service->history($student, (int) ($request->per_page ?? 15));

        $history->getCollection()->transform(fn ($attendance) => $this->service->present($attendance));

        return response()->json($history);
    }

    public function requestIzin(Request $request)
    {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $request->validate([
            'type' => ['required', 'in:izin,sakit'],
            'reason' => ['required', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $today = now()->toDateString();

        if ($student->attendance()->where('date', $today)->exists()) {
            return response()->json(['message' => 'Anda sudah memiliki catatan absensi hari ini.'], 422);
        }

        $photoFile = $request->hasFile('photo') ? $request->file('photo') : null;

        $attendance = $this->attendanceService->recordManual(
            $student,
            $today,
            $request->type,
            $photoFile,
            $request->reason,
        );

        return response()->json([
            'message' => ucfirst($request->type) . ' berhasil dicatat.',
            'data' => $this->service->present($attendance),
        ], 201);
    }

    public function schedules(Request $request)
    {
        $student = $request->user()->student;

        if ($student === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $this->service->schedules($student)]);
    }
}