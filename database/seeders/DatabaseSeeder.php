<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Profesional: profesional@gmail.com
        $profUser = User::where('email', 'profesional@gmail.com')->first();
        if ($profUser && $profUser->professionalProfile) {
            $profile = $profUser->professionalProfile;
            $profile->update([
                'working_days' => ['Lunes', 'Martes', 'Jueves', 'Sábado']
            ]);

            // Limpiamos servicios existentes para evitar duplicaciones
            $profile->services()->delete();

            // Servicios del Mockup
            $profile->services()->createMany([
                [
                    'name' => 'Corte y Barba',
                    'duration_minutes' => 60,
                    'price' => 15000.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Solo Barba',
                    'duration_minutes' => 30,
                    'price' => 8000.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Solo corte',
                    'duration_minutes' => 45,
                    'price' => 10000.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Lifting de cejas',
                    'duration_minutes' => 60,
                    'price' => 12000.00,
                    'is_active' => false,
                ],
                [
                    'name' => 'Spa facial',
                    'duration_minutes' => 90,
                    'price' => 25000.00,
                    'is_active' => false,
                ],
            ]);
        }

        // 2. Profesional: juan@gmail.com
        $juanUser = User::where('email', 'juan@gmail.com')->first();
        if ($juanUser && $juanUser->professionalProfile) {
            $profile = $juanUser->professionalProfile;
            $profile->update([
                'working_days' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes']
            ]);

            $profile->services()->delete();
            $profile->services()->createMany([
                [
                    'name' => 'Corte clásico',
                    'duration_minutes' => 30,
                    'price' => 7000.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Afeitado tradicional',
                    'duration_minutes' => 30,
                    'price' => 6000.00,
                    'is_active' => true,
                ],
            ]);
        }
    }
}
