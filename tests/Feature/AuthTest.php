<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.sch.id',
            'password' => 'secret123',
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'admin@test.sch.id',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'admin@test.sch.id',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => 'admin@test.sch.id',
            'password' => 'salah',
        ])->assertStatus(422);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'admin@test.sch.id',
            'password' => 'secret123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'identifier' => 'admin@test.sch.id',
                'password' => 'wrong'.$i,
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'identifier' => 'admin@test.sch.id',
            'password' => 'wrong6',
        ])->assertStatus(429);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_GURU]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'guru');
    }

    public function test_student_cannot_access_admin_routes(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_SISWA]);
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/overview')
            ->assertForbidden();

        $this->withToken($token)->getJson('/api/teacher/classes')
            ->assertForbidden();
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_student_can_login_with_name_and_default_password(): void
    {
        $user = User::factory()->create([
            'name' => 'BUDI SANTOSO',
            'email' => 'xtkj-0001@siswa.sch.id',
            'password' => 'siswa123',
            'role' => User::ROLE_SISWA,
        ]);
        \App\Models\Student::factory()->create([
            'user_id' => $user->id,
            'nis' => 'XTKJ-0001',
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => 'BUDI SANTOSO',
            'password' => 'siswa123',
        ])->assertOk()->assertJsonPath('user.role', 'siswa');

        $this->postJson('/api/auth/login', [
            'identifier' => 'XTKJ-0001',
            'password' => 'siswa123',
        ])->assertOk()->assertJsonPath('user.role', 'siswa');
    }

    public function test_login_with_unknown_identifier_rejected(): void
    {
        User::factory()->create([
            'name' => 'BUDI SANTOSO',
            'password' => 'siswa123',
            'role' => User::ROLE_SISWA,
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => 'TIDAK ADA',
            'password' => 'siswa123',
        ])->assertStatus(422);
    }
}
