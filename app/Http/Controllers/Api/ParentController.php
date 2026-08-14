<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ParentService;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(private readonly ParentService $service)
    {
    }

    public function children(Request $request)
    {
        $guardian = $request->user()->guardian;

        if ($guardian === null) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->service->children($guardian)]);
    }

    public function childAttendance(Request $request, int $student)
    {
        $guardian = $request->user()->guardian;

        $child = Student::with('user', 'classRoom')->find($student);

        if ($child === null) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        if ($guardian === null || $guardian->students()->whereKey($child->id)->doesntExist()) {
            throw new AuthorizationException('Anda hanya dapat melihat data anak Anda sendiri.');
        }

        $attendance = $this->service->childAttendance($child, $request->month);

        return response()->json([
            'data' => [
                'student' => [
                    'id' => $child->id,
                    'name' => $child->user->name,
                    'nis' => $child->nis,
                    'class' => $child->classRoom?->name,
                ],
                'attendance' => $attendance,
            ],
        ]);
    }
}