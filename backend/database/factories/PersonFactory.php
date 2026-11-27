<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Person> */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'person_no' => sprintf('PER-T%06d', $n + random_int(0, 899999)),
            'surname' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '080'.fake()->unique()->numerify('########'),
        ];
    }
}
