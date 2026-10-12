<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        // Only superadmin can manage users
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $users]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        // Only superadmin can create users
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['superadmin', 'homeadvisor', 'user'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(), // Auto verify for admin created users
        ]);

        return response()->json(['message' => 'Pengguna berhasil ditambahkan.', 'data' => $user], 201);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['superadmin', 'homeadvisor', 'user'])],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['message' => 'Pengguna berhasil diperbarui.', 'data' => $user]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::findOrFail($id);
        
        // Prevent deleting oneself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun Anda sendiri.'], 400);
        }

        // Reassign any listings to the superadmin performing deletion to prevent cascade loss
        \App\Models\Listing::where('user_id', $user->id)->update(['user_id' => $request->user()->id]);

        $user->delete();

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }
}
