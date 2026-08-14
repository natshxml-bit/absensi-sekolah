<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Major;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassRoom>
 */
class ClassRoomFactory extends Factory
{
    protected $model = ClassRoom::class;

    public function definition(): array
    {
        return [
            'name' => 'X IPA 1',
            'major_id' => Major::factory(),
        ];
    }
}
