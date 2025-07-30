<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Administrateur',
            'email' => 'admin@tribunal.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);

        User::create([
            'name' => 'Archiviste',
            'email' => 'archiviste@tribunal.com',
            'password' => Hash::make('password'),
            'role' => 'archiviste'
        ]);

        User::create([
            'name' => 'Utilisateur',
            'email' => 'user@tribunal.com',
            'password' => Hash::make('password'),
            'role' => 'utilisateur'
        ]);
    }
} 