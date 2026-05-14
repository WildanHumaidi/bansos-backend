<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        
        User::create([
            'name' => 'Administrator Sistem',
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role' => 'admin'
        ]);

        
        User::create([
            'name' => 'Bapak Ketua RW 08',
            'username' => 'rw08',
            'password' => Hash::make('rw123'),
            'role' => 'rw'
        ]);

        
        $rts = ['001', '002', '003'];
        foreach ($rts as $rt) {
            User::create([
                'name' => 'Ketua RT ' . $rt,
                'username' => 'rt' . $rt,
                'password' => Hash::make('rt123'),
                'role' => 'rt',
                'rt' => $rt
            ]);
        }
    }
}