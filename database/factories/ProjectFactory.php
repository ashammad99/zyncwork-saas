<?php

namespace Database\Factories;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Closures so overridden keys never query (and never crash on an empty table).
            'department_id' => fn () => Department::inRandomOrder()->value('id'),
            'owner_id' => fn () => User::inRandomOrder()->value('id'),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['active', 'on_hold', 'archived']),
            'deadline' => fake()->date(),
        ];
    }
}
