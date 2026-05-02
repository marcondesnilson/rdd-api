<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador RDD',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ],
        );
        $this->syncUserDetails($admin, 'AD', 'admin', 'Administrador da República do Direito');

        $editor = User::query()->updateOrCreate(
            ['email' => 'editor@admin.com'],
            [
                'name' => 'Editor RDD',
                'password' => Hash::make('editor123'),
                'email_verified_at' => now(),
            ],
        );
        $this->syncUserDetails($editor, 'ED', 'editor', 'Editor da República do Direito');
    }

    private function syncUserDetails(User $user, string $initials, string $role, string $headline): void
    {
        $user->profile()->updateOrCreate([], [
            'initials' => $initials,
            'headline' => $headline,
        ]);
        $user->preferences()->updateOrCreate([], []);
        $user->roleRecord()->updateOrCreate([], ['role' => $role]);
        $user->verification()->updateOrCreate([], ['status' => 'approved']);
    }
}
