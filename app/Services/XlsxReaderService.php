<?php

namespace App\Services;

use SimpleXMLElement;
use ZipArchive;

class XlsxReaderService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Membaca file rekap absensi (.xlsx) dan mengekstrak data siswa per kelas.
     * Format: tiap sheet = satu kelas; baris kolom "Nama Siswa" + kolom berikutnya "L/P".
     *
     * @return array<int, array{class: string, students: array<int, array{name: string, gender: string}>}>
     */
    public function readStudentRecap(string $path): array
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException('File excel tidak ditemukan.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('File bukan .xlsx yang valid.');
        }

        try {
            $strings = $this->sharedStrings($zip);
            $sheetFiles = $this->sheetFiles($zip);

            $result = [];
            foreach ($sheetFiles as $name => $entry) {
                $xml = @simplexml_load_string($zip->getFromName($entry));
                if ($xml === false) {
                    continue;
                }

                $students = $this->extractStudents($xml, $strings);
                if (empty($students)) {
                    continue;
                }

                $result[] = [
                    'class' => trim($name),
                    'students' => $students,
                ];
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = @simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $si) {
            $text = '';
            foreach ($si->t as $t) {
                $text .= (string) $t;
            }
            if ($text === '') {
                foreach ($si->r->t as $t) {
                    $text .= (string) $t;
                }
            }
            $strings[] = trim($text);
        }

        return $strings;
    }

    /** @return array<string, string> map nama sheet => path file */
    private function sheetFiles(ZipArchive $zip): array
    {
        $wb = @simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = @simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($wb === false || $rels === false) {
            return [];
        }

        $map = [];
        foreach ($rels->Relationship as $rel) {
            $attrs = (array) $rel->attributes();
            $attrs = $attrs['@attributes'] ?? [];
            if (preg_match('#worksheets/([^/]+\.xml)$#', $attrs['Target'] ?? '', $m)) {
                $map[$attrs['Id']] = 'xl/worksheets/'.$m[1];
            }
        }

        $sheets = [];
        foreach ($wb->sheets->sheet as $sheet) {
            $name = trim((string) $sheet['name']);
            $rid = (string) $sheet->attributes(self::NS_REL)['id'] ?? null;
            if ($rid !== '' && isset($map[$rid])) {
                $sheets[$name] = $map[$rid];
            }
        }

        return $sheets;
    }

    /** @param array<int, string> $strings */
    private function extractStudents(SimpleXMLElement $sheet, array $strings): array
    {
        $students = [];
        $headerCol = null;
        $nameCol = null;
        $dataStarted = false;

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $colLetters = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = preg_replace('/\d+$/', '', $ref);
                $cells[$col] = $this->cellValue($cell, $strings);
                $colLetters[] = $col;
            }

            if (! $dataStarted) {
                foreach ($cells as $col => $value) {
                    if (preg_match('/^Nama\s+Siswa$/i', trim((string) $value))) {
                        $headerCol = $col;
                        $dataStarted = true;
                        break;
                    }
                }
                continue;
            }

            $nameCol = $headerCol;
            $genderCol = $this->nextColumn($nameCol);

            $name = trim((string) ($cells[$nameCol] ?? ''));
            $gender = strtolower(trim((string) ($cells[$genderCol] ?? '')));

            if ($name === '' || in_array($name, ['Laki-Laki', 'Laki - Laki', 'Perempuan', 'Total', 'JUMLAH'], true)) {
                continue;
            }
            if ($gender !== 'l' && $gender !== 'p') {
                continue;
            }

            $students[] = ['name' => $name, 'gender' => $gender];
        }

        return $students;
    }

    private function cellValue(SimpleXMLElement $cell, array $strings): string|int|float|null
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($cell->is->t as $t) {
                $parts[] = (string) $t;
            }

            return implode('', $parts);
        }

        $v = $cell->v;
        if ($v === null) {
            return null;
        }

        if ($type === 's') {
            $idx = (int) (string) $v;
            return $strings[$idx] ?? null;
        }

        $raw = trim((string) $v);
        if ($raw === '') {
            return null;
        }

        return preg_match('/^\d+$/', $raw) ? (int) $raw : (float) $raw;
    }

    private function nextColumn(string $col): string
    {
        $n = 0;
        foreach (str_split(strtoupper($col)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        $n++;

        $out = '';
        while ($n > 0) {
            $n--;
            $out = chr(65 + ($n % 26)).$out;
            $n = intdiv($n, 26);
        }

        return $out;
    }
}