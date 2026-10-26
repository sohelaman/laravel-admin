<?php

namespace Tests\Seeds;

use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    public function run()
    {
        \Tests\Models\User::factory()->count(50)
            ->create()
            ->each(function ($u) {
                $u->profile()->save(\Tests\Models\Profile::factory()->make());
                $u->tags()->saveMany(\Tests\Models\Tag::factory()->count(5)->make());
                $u->data = ['json' => ['field' => random_int(0, 50)]];
            });
    }
}
