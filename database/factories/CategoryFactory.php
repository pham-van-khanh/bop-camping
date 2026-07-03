<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = 'Danh mục '.fake()->unique()->numberBetween(1, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
