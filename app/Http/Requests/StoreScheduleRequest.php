<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'day' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'class_id.required' => 'Kelas wajib dipilih.',
            'teacher_id.required' => 'Guru wajib dipilih.',
            'subject.required' => 'Mata pelajaran wajib diisi.',
            'day.required' => 'Hari wajib dipilih.',
            'day.in' => 'Hari tidak valid.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'end_time.required' => 'Jam selesai wajib diisi.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }
}