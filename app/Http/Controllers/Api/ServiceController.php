<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ProfessionalProfile;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of the professional's services.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->professionalProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'El usuario no tiene un perfil profesional asociado.'
            ], 403);
        }

        $services = $profile->services()->orderBy('id', 'asc')->get();

        return response()->json($services);
    }

    /**
     * Store a newly created service.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $profile = $user->professionalProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'El usuario no tiene un perfil profesional asociado.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:5|multiple_of:5',
            'price' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ], [
            'duration_minutes.multiple_of' => 'La duración debe ser un múltiplo de 5 minutos.',
        ]);

        $service = $profile->services()->create($validated);

        return response()->json([
            'message' => 'Servicio creado con éxito',
            'service' => $service
        ], 201);
    }

    /**
     * Update the specified service.
     */
    public function update(Request $request, Service $service)
    {
        $user = $request->user();
        $profile = $user->professionalProfile;

        if (!$profile || $service->professional_profile_id !== $profile->id) {
            return response()->json([
                'message' => 'No tienes autorización para modificar este servicio.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'duration_minutes' => 'sometimes|required|integer|min:5|multiple_of:5',
            'price' => 'sometimes|required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ], [
            'duration_minutes.multiple_of' => 'La duración debe ser un múltiplo de 5 minutos.',
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Servicio actualizado con éxito',
            'service' => $service
        ]);
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Request $request, Service $service)
    {
        $user = $request->user();
        $profile = $user->professionalProfile;

        if (!$profile || $service->professional_profile_id !== $profile->id) {
            return response()->json([
                'message' => 'No tienes autorización para eliminar este servicio.'
            ], 403);
        }

        $service->delete();

        return response()->json([
            'message' => 'Servicio eliminado con éxito'
        ]);
    }

    /**
     * Get active services of a specific professional (for client role scheduling).
     */
    public function getProfessionalServices(Request $request, $id)
    {
        $profile = ProfessionalProfile::find($id);

        if (!$profile) {
            return response()->json([
                'message' => 'Perfil profesional no encontrado.'
            ], 404);
        }

        // Return only active services
        $services = $profile->services()->where('is_active', true)->orderBy('id', 'asc')->get();

        return response()->json($services);
    }
}
