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
        $isFirst = $user->addresses()->count() === 0;
        $makeDefault = $request->boolean('is_default', $isFirst);

        if ($makeDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => $user->id,
            'address_line' => $request->address_line,
            'alias' => $request->alias,
            'is_default' => $makeDefault,
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

        $address->update([
            'address_line' => $request->address_line,
            'alias' => $request->alias,
        ]);

        if ($request->has('is_default')) {
            $makeDefault = $request->boolean('is_default');
            if ($makeDefault) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
                $address->update(['is_default' => true]);
            }
        }

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
        $isDefault = $address->is_default;

        $address->delete();

        // If the deleted address was the default one, promote another one if available
        if ($isDefault) {
            $first = $user->addresses()->first();
            if ($first) {
                $first->update(['is_default' => true]);
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

        // Set all other addresses to is_default = false
        $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);

        // Set this address as default
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Domicilio marcado como principal',
            'addresses' => $user->addresses()->orderBy('id', 'desc')->get()
        ]);
    }
}
