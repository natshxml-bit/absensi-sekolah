<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class AdminImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');
    }

    public function test_admin_can_import_students_from_recap_xlsx(): void
    {
        $file = UploadedFile::fake()->createWithContent('rekap.xlsx', $this->buildXlsx([
            'X TKJ' => [
                ['BUDI SANTOSO', 'L'],
                ['SITI AMINAH', 'P'],
            ],
        ]));

        $this->postJson('/api/admin/students/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'classes_created' => 1,
                    'students_imported' => 2,
                    'skipped' => 0,
                ],
            ]);

        $this->assertDatabaseHas('classes', ['name' => 'X TKJ']);

        $budi = Student::where('nis', 'XTKJ-0001')->first();
        $this->assertNotNull($budi);
        $this->assertSame('BUDI SANTOSO', $budi->user->name);
        $this->assertSame('l', $budi->gender);

        $siti = Student::where('nis', 'XTKJ-0002')->first();
        $this->assertNotNull($siti);
        $this->assertSame('p', $siti->gender);
        $this->assertSame('xtkj-0002@siswa.sch.id', $siti->user->email);
        $this->assertSame(User::ROLE_SISWA, $siti->user->role);

        $this->assertDatabaseHas('activity_logs', ['action' => 'student.import']);
    }

    public function test_reimport_skips_existing_students(): void
    {
        $content = $this->buildXlsx([
            'X TKJ' => [
                ['BUDI SANTOSO', 'L'],
                ['SITI AMINAH', 'P'],
            ],
        ]);

        $this->postJson('/api/admin/students/import', ['file' => UploadedFile::fake()->createWithContent('rekap.xlsx', $content)])
            ->assertStatus(201);

        $this->postJson('/api/admin/students/import', ['file' => UploadedFile::fake()->createWithContent('rekap.xlsx', $content)])
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'classes_created' => 0,
                    'students_imported' => 0,
                    'skipped' => 2,
                ],
            ]);

        $this->assertSame(2, Student::count());
    }

    public function test_import_creates_two_classes_from_two_sheets(): void
    {
        $file = UploadedFile::fake()->createWithContent('rekap.xlsx', $this->buildXlsx([
            'X AKL' => [['DEWI', 'P']],
            'XI TSM' => [['ROBIN', 'L']],
        ]));

        $this->postJson('/api/admin/students/import', ['file' => $file])->assertStatus(201);

        $this->assertSame(2, ClassRoom::count());
        $this->assertDatabaseHas('students', ['nis' => 'XAKL-0001']);
        $this->assertDatabaseHas('students', ['nis' => 'XITSM-0002']);
    }

    public function test_non_admin_cannot_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_GURU]), 'sanctum');

        $this->postJson('/api/admin/students/import', [
            'file' => UploadedFile::fake()->createWithContent('rekap.xlsx', $this->buildXlsx(['X' => [['A', 'L']]])),
        ])->assertStatus(403);
    }

    public function test_non_xlsx_file_is_rejected(): void
    {
        $this->postJson('/api/admin/students/import', [
            'file' => UploadedFile::fake()->create('rekap.txt', 100),
        ])->assertStatus(422);
    }

    private function buildXlsx(array $sheetsByClass): string
    {
        $shared = ['Nama Siswa', 'L/P'];
        $index = ['Nama Siswa' => 0, 'L/P' => 1];

        foreach ($sheetsByClass as $rows) {
            foreach ($rows as [$nama, $jk]) {
                foreach ([$nama, $jk] as $str) {
                    if (! isset($index[$str])) {
                        $index[$str] = count($shared);
                        $shared[] = $str;
                    }
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $wbSheets = [];
        $wbRels = [];
        $i = 0;

        foreach ($sheetsByClass as $name => $rows) {
            $i++;
            $sheetName = 'sheet'.$i.'.xml';
            $relId = 'rId'.$i;

            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

            for ($r = 1; $r <= 13; $r++) {
                $xml .= '<row r="'.$r.'"><c r="A'.$r.'" t="inlineStr"><is><t>KOP</t></is></c></row>';
            }

            $xml .= '<row r="14">'
                .'<c r="B14" t="s"><v>0</v></c>'
                .'<c r="C14" t="s"><v>1</v></c>'
                .'</row>';

            $no = 0;
            foreach ($rows as [$nama, $jk]) {
                $no++;
                $r = 14 + $no;
                $xml .= '<row r="'.$r.'">'
                    .'<c r="A'.$r.'" t="inlineStr"><is><t>'.$no.'</t></is></c>'
                    .'<c r="B'.$r.'" t="s"><v>'.$index[$nama].'</v></c>'
                    .'<c r="C'.$r.'" t="s"><v>'.$index[$jk].'</v></c>'
                    .'</row>';
            }

            $xml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/'.$sheetName, $xml);
            $wbSheets[] = '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$i.'" r:id="'.$relId.'"/>';
            $wbRels[] = '<Relationship Id="'.$relId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/'.$sheetName.'"/>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.implode('', $wbSheets).'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', $wbRels).'</Relationships>');

        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($shared).'" uniqueCount="'.count($shared).'">';
        foreach ($shared as $s) {
            $ssXml .= '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        $zip->close();
        $content = file_get_contents($path);
        @unlink($path);

        return $content;
    }
}