<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->orderBy('id', 'desc')->get();

        return response()->json($addresses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'address_line' => 'required|string|max:255',
            'alias' => 'required|string|max:100',
        ]);

        $user = $request->user();

        // If the new address is marked as Principal, rename existing Principal
        if (strtolower($request->alias) === 'principal') {
            $user->addresses()->where('alias', 'Principal')->update(['alias' => 'Casa']);
            $alias = 'Principal';
        } else {
            $alias = $request->alias;
        }

        $address = Address::create([
            'user_id' => $user->id,
            'address_line' => $request->address_line,
            'alias' => $alias,
        ]);

        // Return updated list of addresses
        return response()->json([
            'message' => 'Domicilio agregado con éxito',
            'address' => $address,
            'addresses' => $user->addresses()->orderBy('id', 'desc')->get()
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address)
    {
        // Authorize
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'address_line' => 'required|string|max:255',
            'alias' => 'required|string|max:100',
        ]);

        $user = $request->user();

        // If updated to Principal, rename existing Principal
        if (strtolower($request->alias) === 'principal' && strtolower($address->alias) !== 'principal') {
            $user->addresses()->where('alias', 'Principal')->update(['alias' => 'Casa']);
            $alias = 'Principal';
        } else {
            $alias = $request->alias;
        }

        $address->update([
            'address_line' => $request->address_line,
            'alias' => $alias,
        ]);

        return response()->json([
            'message' => 'Domicilio actualizado con éxito',
            'address' => $address,
            'addresses' => $user->addresses()->orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Address $address)
    {
        // Authorize
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $user = $request->user();
        $isPrincipal = strtolower($address->alias) === 'principal';

        $address->delete();

        // If the deleted address was the principal one, promote another one if available
        if ($isPrincipal) {
            $first = $user->addresses()->first();
            if ($first) {
                $first->update(['alias' => 'Principal']);
            }
        }

        return response()->json([
            'message' => 'Domicilio eliminado con éxito',
            'addresses' => $user->addresses()->orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * Set the specified address as principal.
     */
    public function setPrincipal(Request $request, Address $address)
    {
        // Authorize
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $user = $request->user();

        // Rename current principal to Casa
        $user->addresses()->where('alias', 'Principal')->update(['alias' => 'Casa']);

        // Set this address as Principal
        $address->update(['alias' => 'Principal']);

        return response()->json([
            'message' => 'Domicilio marcado como principal',
            'addresses' => $user->addresses()->orderBy('id', 'desc')->get()
        ]);
    }
}
