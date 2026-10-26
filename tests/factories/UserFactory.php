<?php

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'username' => fake()->userName(),
            'email'    => fake()->email(),
            'mobile'   => fake()->phoneNumber(),
            'avatar'   => fake()->imageUrl(),
            'password' => '$2y$10$U2WSLymU6eKJclK06glaF.Gj3Sw/ieDE3n7mJYjKEgDh4nzUiSESO', // bcrypt(123456)
        ];
    }
}
