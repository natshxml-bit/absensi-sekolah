<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $class = ClassRoom::factory()->create();

        $this->student = Student::factory()->create(['class_id' => $class->id]);
        $this->user = $this->student->user;

        Setting::set(Setting::KEY_LATITUDE, '-6.2087634');
        Setting::set(Setting::KEY_LONGITUDE, '106.845599');
        Setting::set(Setting::KEY_RADIUS_METERS, '100');
        Setting::set(Setting::KEY_LATE_TIME, '23:59');
    }

    private function fakeSelfie(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'selfie.jpg',
            base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q=='),
        );
    }

    public function test_checkin_success_with_photo_and_gps(): void
    {
        $photo = $this->fakeSelfie();

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/student/attendance', [
            'photo' => $photo,
            'latitude' => '-6.2087634',
            'longitude' => '106.845599',
            'device_info' => 'UnitTest/Android',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'hadir')
            ->assertJsonPath('data.latitude', -6.2087634);

        $attendance = Attendance::first();
        $this->assertNotNull($attendance);
        $this->assertSame($this->user->id, $attendance->student->user_id);
        $this->assertSame(now()->toDateString(), $attendance->date->format('Y-m-d'));
        $this->assertNotNull($attendance->check_in_time);
        $this->assertNotNull($attendance->photo);
        $this->assertStringContainsString('attendance/', $attendance->photo);

        $this->assertDatabaseHas('activity_logs', ['action' => 'checkin']);
    }

    public function test_checkin_rejected_outside_radius(): void
    {
        $photo = $this->fakeSelfie();

        $this->actingAs($this->user, 'sanctum')->postJson('/api/student/attendance', [
            'photo' => $photo,
            'latitude' => '-6.3000000',
            'longitude' => '106.9000000',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Anda berada di luar radius absensi sekolah (jarak 11.79 km).');

        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_checkin_rejected_duplicate_same_day(): void
    {
        Attendance::create([
            'student_id' => $this->student->id,
            'date' => now()->toDateString(),
            'status' => Attendance::STATUS_HADIR,
        ]);

        $photo = $this->fakeSelfie();

        $this->actingAs($this->user, 'sanctum')->postJson('/api/student/attendance', [
            'photo' => $photo,
            'latitude' => '-6.2087634',
            'longitude' => '106.845599',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Siswa sudah melakukan absensi hari ini.');

        $this->assertDatabaseCount('attendance', 1);
    }

    public function test_checkin_requires_photo_and_coordinates(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/student/attendance', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo', 'latitude', 'longitude']);
    }

    public function test_status_is_late_after_late_time(): void
    {
        Setting::set(Setting::KEY_LATE_TIME, '00:00');

        $photo = $this->fakeSelfie();

        $this->actingAs($this->user, 'sanctum')->postJson('/api/student/attendance', [
            'photo' => $photo,
            'latitude' => '-6.2087634',
            'longitude' => '106.845599',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'terlambat');
    }

    public function test_student_sees_own_history_only(): void
    {
        $other = Student::factory()->create();
        Attendance::create([
            'student_id' => $other->id,
            'date' => now()->toDateString(),
            'status' => Attendance::STATUS_HADIR,
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/student/attendance')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
