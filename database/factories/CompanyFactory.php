<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_user_id' => User::factory(),
            'legal_name' => $name,
            'display_name' => $name,
            'slug' => Str::slug($name),
            'tax_id' => fake()->optional()->numerify('#########'),
            'status' => RecordStatus::Active->value,
        ];
    }
}
