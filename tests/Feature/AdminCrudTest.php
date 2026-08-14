<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Guardian;
use App\Models\Major;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');
    }

    public function test_admin_can_create_class_with_major_autocreate(): void
    {
        $this->postJson('/api/admin/classes', [
            'name' => 'XI IPA 2',
            'major_name' => 'IPA',
        ])->assertStatus(201);

        $this->assertDatabaseHas('classes', ['name' => 'XI IPA 2']);
        $this->assertDatabaseHas('majors', ['name' => 'IPA']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'class.create']);
    }

    public function test_admin_can_create_student_with_parent_account(): void
    {
        $class = ClassRoom::factory()->create();

        $this->postJson('/api/admin/students', [
            'name' => 'Rina',
            'email' => 'rina@test.sch.id',
            'password' => 'secret123',
            'nis' => '2026001',
            'class_id' => $class->id,
            'gender' => 'p',
            'parent_name' => 'Ibu Rina',
            'parent_email' => 'ibu.rina@test.sch.id',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'rina@test.sch.id', 'role' => 'siswa']);
        $this->assertDatabaseHas('users', ['email' => 'ibu.rina@test.sch.id', 'role' => 'orangtua']);
        $this->assertDatabaseHas('students', ['nis' => '2026001']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.create']);
    }

    public function test_student_duplicate_nis_rejected(): void
    {
        $class = ClassRoom::factory()->create();

        $this->postJson('/api/admin/students', [
            'name' => 'A', 'email' => 'a@test.sch.id', 'password' => 'secret123',
            'nis' => '2026001', 'class_id' => $class->id,
        ])->assertStatus(201);

        $this->postJson('/api/admin/students', [
            'name' => 'B', 'email' => 'b@test.sch.id', 'password' => 'secret123',
            'nis' => '2026001', 'class_id' => $class->id,
        ])->assertStatus(422);
    }

    public function test_admin_can_create_teacher(): void
    {
        $this->postJson('/api/admin/teachers', [
            'name' => 'Pak Guru',
            'email' => 'guru@test.sch.id',
            'password' => 'secret123',
            'nip' => '198501012000001',
            'gender' => 'l',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'guru@test.sch.id', 'role' => 'guru']);
        $this->assertDatabaseHas('teachers', ['nip' => '198501012000001']);
    }

    public function test_admin_can_update_school_settings(): void
    {
        $this->putJson('/api/admin/settings', [
            'school_name' => 'SMA Nusantara',
            'latitude' => '-6.2',
            'longitude' => '106.8',
            'radius_meters' => '150',
            'late_time' => '07:45',
        ])->assertOk();

        $this->assertSame('SMA Nusantara', Setting::get(Setting::KEY_SCHOOL_NAME));
        $this->assertSame('150', Setting::get(Setting::KEY_RADIUS_METERS));
    }

    public function test_admin_can_record_manual_attendance(): void
    {
        $class = ClassRoom::factory()->create();
        $student = \App\Models\Student::factory()->create(['class_id' => $class->id]);

        $this->postJson('/api/admin/attendance/manual', [
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'status' => 'sakit',
            'notes' => 'Demam',
        ])->assertStatus(201);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'status' => 'sakit',
        ]);

        $this->postJson('/api/admin/attendance/manual', [
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'status' => 'izin',
        ])->assertStatus(422);
    }

    public function test_admin_attendance_index_with_filters(): void
    {
        $class = ClassRoom::factory()->create();
        $student = \App\Models\Student::factory()->create(['class_id' => $class->id]);
        $student->attendance()->create(['date' => '2026-08-11', 'status' => 'hadir']);
        $student->attendance()->create(['date' => '2026-08-12', 'status' => 'alfa']);

        $this->getJson('/api/admin/attendance?status=alfa')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/attendance?class_id='.$class->id.'&date=2026-08-11')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_admin_cannot_access_admin_crud(): void
    {
        $this->app->make('auth')->forgetGuards();
        $student = User::factory()->create(['role' => User::ROLE_SISWA]);
        $this->actingAs($student, 'sanctum');

        $this->postJson('/api/admin/classes', ['name' => 'X', 'major_name' => 'Y'])
            ->assertForbidden();
    }
}
