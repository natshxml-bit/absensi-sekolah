<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Teacher;
use App\Models\ClassRoom;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use SimpleXMLElement;
use ZipArchive;

class ImportTeachersCommand extends Command
{
    protected $signature = 'import:teachers {path=/sdcard/jadwalkbm.xlsx}';
    protected $description = 'Import teachers and schedules from xlsx';

    private array $strings = [];
    private array $classMap = [
        'X AKL' => 1, 'X TSM' => 4, 'X TKJ Pa' => 2, 'X TKJ Pi' => 3,
        'XI AKL' => 5, 'XI TSM' => 8, 'XI TKJ Pa' => 6, 'XI TKJ Pi' => 7,
        'XII AKL' => 9, 'XII TSM' => 10, 'XII TKJ Pa' => 11, 'XII TKJ Pi' => 12,
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return 1;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $this->error('File bukan .xlsx valid.');
            return 1;
        }

        $this->strings = $this->sharedStrings($zip);
        $xml = @simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        if ($xml === false) {
            $this->error('Gagal baca sheet1.');
            return 1;
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = preg_replace('/\d+$/', '', $ref);
                $cells[$col] = $this->cellValue($c);
            }
            $rows[] = $cells;
        }

        $teachers = $this->parseTeachers($rows);

        $this->info("Ditemukan " . count($teachers) . " guru. Mengimpor...");

        $created = 0;
        foreach ($teachers as $t) {
            $user = User::firstOrCreate(
                ['email' => $t['email']],
                [
                    'name' => $t['name'],
                    'password' => Hash::make('guru123'),
                    'role' => User::ROLE_GURU,
                ]
            );

            if ($user->wasRecentlyCreated) {
                Teacher::create([
                    'user_id' => $user->id,
                    'nip' => $t['nip'],
                    'gender' => 'L',
                    'phone' => '',
                    'address' => '',
                ]);
                $created++;
            }

            $teacher = $user->teacher;
            if ($teacher) {
                foreach ($t['schedules'] as $s) {
                    Schedule::firstOrCreate([
                        'teacher_id' => $teacher->id,
                        'class_id' => $s['class_id'],
                        'subject' => $s['subject'],
                        'day' => $s['day'],
                        'start_time' => $s['start_time'],
                        'end_time' => $s['end_time'],
                    ]);
                }
            }

            $this->line("  {$t['name']} → " . count($t['schedules']) . " jadwal");
        }

        $this->info("Selesai! {$created} guru baru dibuat.");
        return 0;
    }

    private function parseTeachers(array $rows): array
    {
        $teachers = [];
        $currentTeacher = null;

        $classKeys = array_keys($this->classMap);

        foreach ($rows as $i => $row) {
            if ($i < 4) continue;

            $no = trim((string) ($row['A'] ?? ''));
            $name = trim((string) ($row['B'] ?? ''));
            $tugas = trim((string) ($row['C'] ?? ''));
            $subject = trim((string) ($row['E'] ?? ''));

            if ($no !== '' && $name !== '') {
                if ($currentTeacher) {
                    $teachers[] = $currentTeacher;
                }

                if ($tugas === 'Kepala Sekolah') {
                    $currentTeacher = null;
                    continue;
                }

                $currentTeacher = [
                    'no' => $no,
                    'name' => $name,
                    'tugas' => $tugas,
                    'nip' => str_pad($no, 10, '0', STR_PAD_LEFT),
                    'email' => $this->slug($name),
                    'schedules' => [],
                ];
            }

            if ($currentTeacher && $subject !== '') {
                $colIndex = 0;
                foreach ($classKeys as $classKey) {
                    $col = chr(70 + $colIndex);
                    $val = (int) ($row[$col] ?? 0);
                    if ($val > 0) {
                        $currentTeacher['schedules'] = array_merge(
                            $currentTeacher['schedules'],
                            $this->generateSchedule($this->classMap[$classKey], $subject, $val)
                        );
                    }
                    $colIndex++;
                }
            }
        }

        if ($currentTeacher) {
            $teachers[] = $currentTeacher;
        }

        return $teachers;
    }

    private function generateSchedule(int $classId, string $subject, int $hours): array
    {
        $schedules = [];
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $slots = [
            ['07:00', '08:30'],
            ['08:30', '10:00'],
            ['10:00', '11:30'],
            ['13:00', '14:30'],
            ['14:30', '16:00'],
        ];

        $hoursLeft = $hours;
        foreach ($days as $day) {
            if ($hoursLeft <= 0) break;
            foreach ($slots as $slot) {
                if ($hoursLeft <= 0) break;
                $schedules[] = [
                    'class_id' => $classId,
                    'subject' => $subject,
                    'day' => $day,
                    'start_time' => $slot[0],
                    'end_time' => $slot[1],
                ];
                $hoursLeft -= 2;
            }
        }

        return $schedules;
    }

    private function slug(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = @simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
        if ($xml === false) return [];
        $strings = [];
        foreach ($xml->si as $si) {
            $text = '';
            foreach ($si->t as $t) $text .= (string) $t;
            if ($text === '') foreach ($si->r->t as $t) $text .= (string) $t;
            $strings[] = trim($text);
        }
        return $strings;
    }

    private function cellValue(SimpleXMLElement $cell): string|int|float|null
    {
        $type = (string) $cell['t'];
        $v = $cell->v;
        if ($v === null) return null;
        if ($type === 's') return $this->strings[(int) (string) $v] ?? null;
        $raw = trim((string) $v);
        if ($raw === '') return null;
        return preg_match('/^\d+$/', $raw) ? (int) $raw : (float) $raw;
    }
}
