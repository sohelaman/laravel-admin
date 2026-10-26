<?php

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Models\Profile;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition()
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name'  => fake()->lastName(),
            'postcode'   => fake()->postcode(),
            'address'    => fake()->address(),
            'latitude'   => fake()->latitude(),
            'longitude'  => fake()->longitude(),
            'color'      => fake()->hexColor(),
            'start_at'   => fake()->dateTime(),
            'end_at'     => fake()->dateTime(),
        ];
    }
}
