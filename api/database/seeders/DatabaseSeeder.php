<?php

namespace Database\Seeders;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // A little history for the demo / admin stats.
        Summary::factory()->count(3)->completed()->for($user)->create();
        Summary::factory()->failed()->for($user)->create();
        Summary::factory()->completed()->for($admin)->create();
    }
}
