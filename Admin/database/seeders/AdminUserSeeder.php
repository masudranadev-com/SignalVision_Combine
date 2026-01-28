<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\AdminUser::create([
            'username' => 'admin',
            'email' => 'admin@signalvision.ai',
            'password' => bcrypt('00000000'),
            'full_name' => 'Admin User',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
