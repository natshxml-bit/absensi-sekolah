<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherParentTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_only_own_classes(): void
    {
        $teacher = Teacher::factory()->create();
        $other = Teacher::factory()->create();

        $class = ClassRoom::factory()->create();
        $otherClass = ClassRoom::factory()->create();

        Schedule::create([
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Matematika',
            'day' => 'senin',
            'start_time' => '07:00',
            'end_time' => '08:40',
        ]);
        Schedule::create([
            'class_id' => $otherClass->id,
            'teacher_id' => $other->id,
            'subject' => 'Fisika',
            'day' => 'senin',
            'start_time' => '07:00',
            'end_time' => '08:40',
        ]);

        $this->actingAs($teacher->user, 'sanctum')->getJson('/api/teacher/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $class->name);
    }

    public function test_teacher_cannot_view_class_not_taught(): void
    {
        $teacher = Teacher::factory()->create();
        $class = ClassRoom::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);
        $student->attendance()->create(['date' => now()->toDateString(), 'status' => 'hadir']);

        $this->actingAs($teacher->user, 'sanctum')
            ->getJson('/api/teacher/classes/'.$class->id.'/attendance')
            ->assertForbidden();
    }

    public function test_teacher_views_class_attendance_with_photos(): void
    {
        $teacher = Teacher::factory()->create();
        $class = ClassRoom::factory()->create();
        Schedule::create([
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Matematika',
            'day' => 'senin',
            'start_time' => '07:00',
            'end_time' => '08:40',
        ]);
        $student = Student::factory()->create(['class_id' => $class->id]);
        $student->attendance()->create([
            'date' => now()->toDateString(),
            'status' => 'hadir',
            'check_in_time' => '07:15:00',
            'photo' => 'attendance/2026-08-14/x.jpg',
        ]);

        $this->actingAs($teacher->user, 'sanctum')
            ->getJson('/api/teacher/classes/'.$class->id.'/attendance')
            ->assertOk()
            ->assertJsonPath('data.students.0.attendance.status', 'hadir')
            ->assertJsonPath('data.students.0.attendance.photo_url', 'http://localhost:8000/storage/attendance/2026-08-14/x.jpg');
    }

    public function test_parent_sees_only_own_children(): void
    {
        $guardian = Guardian::factory()->create();
        $otherGuardian = Guardian::factory()->create();
        $class = ClassRoom::factory()->create();

        $child = Student::factory()->create(['class_id' => $class->id, 'parent_id' => $guardian->id]);
        $otherChild = Student::factory()->create(['class_id' => $class->id, 'parent_id' => $otherGuardian->id]);

        $this->actingAs($guardian->user, 'sanctum')->getJson('/api/parent/children')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $child->id);

        $this->actingAs($guardian->user, 'sanctum')
            ->getJson('/api/parent/children/'.$otherChild->id.'/attendance')
            ->assertForbidden();
    }

    public function test_parent_views_child_attendance_status(): void
    {
        $guardian = Guardian::factory()->create();
        $class = ClassRoom::factory()->create();
        $child = Student::factory()->create(['class_id' => $class->id, 'parent_id' => $guardian->id]);
        $child->attendance()->create([
            'date' => now()->toDateString(),
            'status' => 'hadir',
            'check_in_time' => '07:10:00',
        ]);

        $this->actingAs($guardian->user, 'sanctum')
            ->getJson('/api/parent/children/'.$child->id.'/attendance')
            ->assertOk()
            ->assertJsonPath('data.student.id', $child->id)
            ->assertJsonPath('data.attendance.0.status', 'hadir');
    }
}
