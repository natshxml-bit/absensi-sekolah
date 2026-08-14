<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => User::ROLE_SISWA]),
            'nis' => fake()->unique()->numerify('##########'),
            'class_id' => ClassRoom::factory(),
            'parent_id' => null,
            'gender' => 'l',
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
        ];
    }

    public function withGuardian(): static
    {
        return $this->state(function () {
            return ['parent_id' => Guardian::factory()];
        });
    }
}
