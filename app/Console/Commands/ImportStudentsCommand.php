<?php

namespace App\Console\Commands;

use App\Models\{User, Student, Teacher, ClassRoom, Major};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportStudentsCommand extends Command
{
    protected $signature = 'import:students';
    protected $description = 'Import students from students_import.json';

    public function handle(): int
    {
        $json = file_get_contents(database_path('students_import.json'));
        $students = json_decode($json, true);

        if (!$students) {
            $this->error('No students found in JSON file.');
            return 1;
        }

        // Create majors
        $majorNames = ['TKJ', 'RPL', 'MM', 'AKL', 'TSM'];
        $majors = [];
        foreach ($majorNames as $name) {
            $majors[$name] = Major::updateOrCreate(['name' => $name], ['code' => $name]);
        }

        // Map class names to major
        $classMajorMap = [
            'X AKL' => 'AKL', 'X TKJ PUTRA' => 'TKJ', 'X TKJ PUTRI' => 'TKJ', 'X TSM' => 'TSM',
            'XI AK' => 'AKL', 'XI TKJ PUTRI' => 'TKJ', 'XI TKJ PUTRA' => 'TKJ', 'XI TSM' => 'TSM',
            'XII AK' => 'AKL', 'XII TSM' => 'TSM', 'XII TKJ PUTRI' => 'TKJ', 'XII TKJ PUTRA' => 'TKJ',
            'X AKL' => 'AKL',
        ];

        // Create classes
        $classes = [];
        $classNames = array_unique(array_column($students, 'class'));
        foreach ($classNames as $name) {
            $majorName = $classMajorMap[$name] ?? 'TKJ';
            $classes[$name] = ClassRoom::updateOrCreate(
                ['name' => $name],
                ['major_id' => $majors[$majorName]->id]
            );
        }

        // Import students
        $count = 0;
        $nisCounter = 1;
        foreach ($students as $s) {
            $className = $s['class'];
            $gender = $s['gender'] === 'L' ? 'L' : 'P';
            $nis = 'YP79-' . str_pad($nisCounter, 3, '0', STR_PAD_LEFT);
            $nisCounter++;

            $email = Str::slug($s['name']) . '@siswa.smk79.sch.id';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $s['name'],
                    'password' => 'siswa123',
                    'role' => User::ROLE_SISWA,
                ]
            );

            Student::updateOrCreate(
                ['nis' => $nis],
                [
                    'user_id' => $user->id,
                    'class_id' => $classes[$className]->id,
                    'gender' => $gender,
                ]
            );
            $count++;
        }

        $this->info("Imported: {$count} students, " . count($classes) . " classes, " . count($majors) . " majors");
        return 0;
    }
}
