<?php

namespace Database\Factories;

use App\Models\Combo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Combo>
 */
class ComboFactory extends Factory
{
    protected $model = Combo::class;

    public function definition(): array
    {
        $name = 'Combo '.fake()->unique()->numberBetween(1, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'combo_price' => 100000,
            'is_active' => true,
        ];
    }
}
