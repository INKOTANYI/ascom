<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\SchoolProfile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a new school installation. Safe to run again: it never overwrites the school's own data.
     */
    public function run(): void
    {
        $this->call([AccessSeeder::class, AcademicStructureSeeder::class]);

        // Placeholder profile for the pilot school; the real details are entered in Settings.
        if (! SchoolProfile::query()->exists()) {
            SchoolProfile::query()->create([
                'name' => 'GS Kagombyi',
                'short_name' => 'GS Kagombyi',
                'school_type' => 'mixed',
                'ownership' => 'public',
                'district' => 'To be completed',
                'sector' => 'To be completed',
            ]);
            Setting::put('example_data', true);
        }

        // First administrator. Its random password is written to a local file, never printed.
        if (! User::query()->where('username', 'admin')->exists()) {
            $password = Str::password(16, symbols: false);
            User::query()->create([
                'name' => 'System administrator',
                'username' => 'admin',
                'password' => $password,
                'role_id' => Role::query()->where('name', 'admin')->value('id'),
                'language' => 'en',
            ]);
            $file = env('ADMIN_PASSWORD_FILE');
            if ($file) {
                file_put_contents($file, "Smart School admin login: admin / {$password}\n", FILE_APPEND);
                $this->command?->info("Admin password written to {$file}");
            } else {
                $this->command?->warn("Admin password: {$password} (store it safely)");
            }
        }
    }
}
