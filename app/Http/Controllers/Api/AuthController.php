<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Address;
use App\Models\ProfessionalProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. validacion de los datos obligatorios de registro
        $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'role' => 'required|in:client,professional',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string',
            'phone' => 'nullable|string',
            
            // direccion obligatoria de cliente, pero no de profesional
            'address_line' => $request->role === 'client' ? 'required|string|max:255' : 'nullable',            // validaciones fija siendo profesional
            'profession' => 'required_if:role,professional|in:Pilates,Barberia,Carpinteria,Electricidad',
            'has_physical_shop' => 'required_if:role,professional|boolean',
            'shop_address' => 'required_if:has_physical_shop,true|nullable|string|max:255',
        ]);

        // creacion de usuario en la tabla 'users'
        $user = User::create([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'birth_date' => $request->birth_date,
            'gender' => $request->gender,
            'phone' => $request->phone,
        ]);

        // si es cliente, guardamos su direccion en 'addresses'
        if ($user->role === 'client') {
            Address::create([
                'user_id' => $user->id,
                'address_line' => $request->address_line,
                'alias' => 'Principal'
            ]);
        }

        // si es profesional, guardamos su perfil en 'professional_profiles'
        if ($user->role === 'professional') {
            ProfessionalProfile::create([
                'user_id' => $user->id,
                'profession' => $request->profession,
                'has_physical_shop' => $request->has_physical_shop,
                'shop_address' => $request->has_physical_shop ? $request->shop_address : null,
            ]);
        }

        // le creamos un token de autenticacion para que pueda usar la app inmediatamente despues de registrarse
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // si el mail no existe o la contraseña no coincide con la que tenemos guardada -> devolvemos error
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401);
        }

        // si está todo bien le damos un token nuevo
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Sesión iniciada correctamente',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
{
    // eliminamos el token utilizado para esta sesion asi se cierra la misma
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'message' => 'Sesion cerrada correctamente'
    ]);
}
}