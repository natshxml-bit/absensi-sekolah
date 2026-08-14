<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'status' => ['nullable', 'string', 'in:hadir,terlambat,izin,sakit,alfa'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}