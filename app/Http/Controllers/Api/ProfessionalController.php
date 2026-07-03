<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    /**
     * Display a listing of registered professionals.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'professional')->with('professionalProfile');

        if ($request->has('profession') && !empty($request->query('profession'))) {
            $profession = $request->query('profession');
            
            // Ignore general search terms that are not actual professions
            if ($profession !== 'Búsqueda' && $profession !== 'Buscar') {
                $normalized = $this->normalizeString($profession);

                $query->whereHas('professionalProfile', function ($q) use ($normalized) {
                    $dbMapping = [
                        'barberia' => 'Barberia',
                        'carpinteria' => 'Carpinteria',
                        'electricidad' => 'Electricidad',
                        'pilates' => 'Pilates',
                    ];
                    
                    $dbValue = isset($dbMapping[$normalized]) ? $dbMapping[$normalized] : $normalized;
                    $q->where('profession', $dbValue);
                });
            }
        }

        // Handle text search query
        if ($request->has('search') && !empty($request->query('search'))) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $users = $query->get();

        // Reverse map raw profession to accented version for the frontend
        $reverseMapping = [
            'Barberia' => 'Barbería',
            'Carpinteria' => 'Carpintería',
            'Electricidad' => 'Electricidad',
            'Pilates' => 'Pilates',
        ];

        // Format user list for React Native client consumption
        $formatted = $users->map(function ($user) use ($reverseMapping) {
            $profile = $user->professionalProfile;
            $rawProfession = $profile ? $profile->profession : '';
            $category = isset($reverseMapping[$rawProfession]) ? $reverseMapping[$rawProfession] : $rawProfession;

            // Retain original mockup stats if user matches seeded professional names
            $mockedData = [
                'Juan Pérez' => ['rating' => 4.9, 'reviews' => 15, 'isTop' => true],
                'Juan gmail' => ['rating' => 4.9, 'reviews' => 15, 'isTop' => true],
                'Fran Perez' => ['rating' => 4.9, 'reviews' => 15, 'isTop' => true],
                'Barbershop' => ['rating' => 3.7, 'reviews' => 12, 'isTop' => false],
                'Robert Draw' => ['rating' => 5.0, 'reviews' => 4, 'isTop' => false],
                'Carlos Gomez' => ['rating' => 4.8, 'reviews' => 22, 'isTop' => true],
                'ElectroHogar' => ['rating' => 4.1, 'reviews' => 8, 'isTop' => false],
                'Luis Martinez' => ['rating' => 4.5, 'reviews' => 14, 'isTop' => false],
                'Ana Silva' => ['rating' => 5.0, 'reviews' => 30, 'isTop' => true],
                'Estudio Zen' => ['rating' => 4.6, 'reviews' => 18, 'isTop' => false],
                'Muebles López' => ['rating' => 4.7, 'reviews' => 45, 'isTop' => true],
                'Mario Rojas' => ['rating' => 4.2, 'reviews' => 7, 'isTop' => false],
                'Diego Torres' => ['rating' => 4.9, 'reviews' => 25, 'isTop' => false],
            ];

            $fullName = trim("{$user->name} {$user->last_name}");
            $data = isset($mockedData[$fullName]) ? $mockedData[$fullName] : [
                'rating' => 5.0,
                'reviews' => 0,
                'isTop' => false
            ];

            return [
                'id' => $user->id,
                'name' => $fullName,
                'rating' => $data['rating'],
                'reviews' => $data['reviews'],
                'isTop' => $data['isTop'],
                'image' => $user->avatar_url,
                'isShop' => $profile ? (bool)$profile->has_physical_shop : false,
                'category' => $category,
                'profession' => $rawProfession,
                'shop_address' => $profile ? $profile->shop_address : null,
                'working_days' => $profile ? $profile->working_days : [],
                'bio' => $profile ? $profile->bio : null,
                'phone' => $user->phone,
                'open_time_1' => $profile ? $profile->open_time_1 : '08:00',
                'close_time_1' => $profile ? $profile->close_time_1 : '12:00',
                'has_second_range' => $profile ? (bool)$profile->has_second_range : false,
                'open_time_2' => $profile ? $profile->open_time_2 : '15:30',
                'close_time_2' => $profile ? $profile->close_time_2 : '21:00',
                'professional_profile_id' => $profile ? $profile->id : null,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Normalize string by removing accents and converting to lowercase.
     */
    private function normalizeString(string $str): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($str, \Normalizer::FORM_D);
            if ($normalized !== false) {
                $withoutAccents = preg_replace('/[\x{0300}-\x{036f}]/u', '', $normalized);
                return strtolower(trim($withoutAccents));
            }
        }
        
        // Fallback translation if Normalizer class is not loaded
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ü' => 'u', 'Ü' => 'U', 'ñ' => 'n', 'Ñ' => 'N'
        ];
        return strtolower(trim(strtr($str, $accents)));
    }
}
