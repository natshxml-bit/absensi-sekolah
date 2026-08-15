<?php

namespace Database\Seeders;

use App\Models\{User, Student, Teacher, ClassRoom, Major, Schedule, Setting};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FullDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $majors = [
            ['name' => 'TKJ', 'code' => 'TKJ'],
            ['name' => 'RPL', 'code' => 'RPL'],
            ['name' => 'MM', 'code' => 'MM'],
        ];

        $majorModels = [];
        foreach ($majors as $m) {
            $majorModels[$m['name']] = Major::updateOrCreate(['name' => $m['name']], $m);
        }

        $classes = [];
        $classData = [];
        foreach (['X', 'XI', 'XII'] as $grade) {
            foreach (['TKJ', 'RPL', 'MM'] as $m) {
                $className = "$grade $m";
                $classes[$className] = ClassRoom::updateOrCreate(
                    ['name' => $className],
                    ['major_id' => $majorModels[$m]->id]
                );
                $classData[] = $className;
            }
        }

        $teachers = [
            ['name' => 'Dedi Supriatna', 'email' => 'dedi supriatna', 'subjects' => ['Pemrograman Web', 'Basis Data']],
            ['name' => 'Rina Mulyani', 'email' => 'rina mulyani', 'subjects' => ['Matematika', 'Fisika']],
            ['name' => 'Acep Saepudin', 'email' => 'acep saepudin', 'subjects' => ['Jaringan Komputer', 'Sistem Operasi']],
            ['name' => 'Yanti Setiawati', 'email' => 'yanti setiawati', 'subjects' => ['Bahasa Indonesia', 'Seni Budaya']],
            ['name' => 'Andi Kuswanto', 'email' => 'andi kuswanto', 'subjects' => ['Pemrograman Dasar', 'Logika Algoritma']],
            ['name' => 'Siti Nurjanah', 'email' => 'siti nurjanah', 'subjects' => ['Bahasa Inggris', 'Komunikasi']],
            ['name' => 'Dedi Kusnadi', 'email' => 'dedi kusnadi', 'subjects' => ['Multimedia', 'Desain Grafis']],
            ['name' => 'Rudi Hartono', 'email' => 'rudi hartono', 'subjects' => ['Teknik Komputer', 'Hardware']],
            ['name' => 'Wati Sumarni', 'email' => 'wati sumarni', 'subjects' => ['IPS', 'PPKn']],
            ['name' => 'Dian Permata', 'email' => 'dian permata', 'subjects' => ['Kimia', 'Biologi']],
        ];

        $teacherModels = [];
        foreach ($teachers as $t) {
            $user = User::updateOrCreate(
                ['email' => $t['email']],
                ['name' => $t['name'], 'password' => 'guru123', 'role' => User::ROLE_GURU]
            );

            $teacher = Teacher::updateOrCreate(
                ['nip' => $t['email']],
                ['user_id' => $user->id]
            );
            $teacherModels[] = $teacher;
        }

        $studentNames = [
            'Aisyah Rahmawati', 'Aliya Permata Sari', 'Anggun Lestari', 'Anisa Putri',
            'Bayu Firmansyah', 'Citra Dewi', 'Dani Kurniawan', 'Dwi Septiani',
            'Eka Saputri', 'Farhan Maulana', 'Gita Puspita Sari', 'Hendra Wijaya',
            'Indah Cahyani', 'Joko Prasetyo', 'Kartika Sari', 'Lintang Ayu',
            'Maya Angelina', 'Nanda Pratama', 'Olivia Putri', 'Putri Rahayu',
            'Rina Wulandari', 'Sari Dewi', 'Tono Sugiarto', 'Ulya Maghfiroh',
            'Vina Oktaviani', 'Winda Agustin', 'Yoga Pratama', 'Zahra Amelia',
        ];

        $studentModels = [];
        $nisCounter = 1;
        foreach ($studentNames as $idx => $name) {
            $classIdx = $idx % count($classData);
            $className = $classData[$classIdx];
            $nis = 'XTSM-' . str_pad($nisCounter, 3, '0', STR_PAD_LEFT);
            $nisCounter++;

            $user = User::updateOrCreate(
                ['email' => strtolower(str_replace(' ', '.', $name)) . '@siswa.smk79.sch.id'],
                ['name' => $name, 'password' => 'siswa123', 'role' => User::ROLE_SISWA]
            );

            $student = Student::updateOrCreate(
                ['nis' => $nis],
                ['user_id' => $user->id, 'class_id' => $classes[$className]->id]
            );
            $studentModels[] = $student;
        }

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $times = [
            ['07:00', '09:00'],
            ['09:00', '11:00'],
            ['11:00', '13:00'],
            ['13:00', '15:00'],
        ];

        foreach ($classData as $className) {
            foreach ($days as $dayIndex => $day) {
                foreach ($times as $timeIndex => $time) {
                    $teacher = $teacherModels[array_rand($teacherModels)];
                    $subjects = ['Pemrograman Web', 'Basis Data', 'Matematika', 'Jaringan Komputer', 'Bahasa Indonesia', 'Bahasa Inggris', 'Multimedia', 'Desain Grafis', 'Sistem Operasi', 'Logika Algoritma'];
                    $subject = $subjects[array_rand($subjects)];

                    Schedule::updateOrCreate(
                        ['class_id' => $classes[$className]->id, 'day' => $day, 'start_time' => $time[0]],
                        [
                            'subject' => $subject,
                            'teacher_id' => $teacher->id,
                            'end_time' => $time[1],
                        ]
                    );
                }
            }
        }

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        echo "Seeded: " . Major::count() . " majors, " .
             ClassRoom::count() . " classes, " .
             Teacher::count() . " teachers, " .
             Student::count() . " students, " .
             Schedule::count() . " schedules\n";
    }
}
