<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Godown;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SetupSeeder extends Seeder
{
    public function run(): void
    {
        // 5 godowns, each with 5 blocks (A to E). Rename them later from the app.
        foreach (range(1, 5) as $n) {
            $godown = Godown::updateOrCreate(
                ['code' => "G{$n}"],
                ['name' => "Godown {$n}"]
            );

            foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
                Block::updateOrCreate(
                    ['godown_id' => $godown->id, 'code' => $letter],
                    ['name' => "Block {$letter}", 'storage_type' => 'dry']
                );
            }
        }

        // 3 outlets
        foreach (range(1, 3) as $n) {
            Outlet::updateOrCreate(
                ['code' => "O{$n}"],
                ['name' => "Outlet {$n}"]
            );
        }

        // First admin user. Change this password after your first login.
        User::unguarded(function () {
            User::updateOrCreate(
                ['email' => 'admin@kilango.com'],
                [
                    'name' => 'System Admin',
                    'password' => Hash::make('admin123'),
                    'role' => 'admin',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
        });
    }
}
