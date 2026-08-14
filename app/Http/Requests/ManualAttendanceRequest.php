<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class ManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'status' => ['required', 'in:'.implode(',', Attendance::MANUAL_STATUSES)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih.',
            'date.required' => 'Tanggal wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status harus izin, sakit, atau alfa.',
        ];
    }
}