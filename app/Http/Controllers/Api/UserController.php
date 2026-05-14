<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Ambil semua data user (Kecuali warga, karena warga ada di tabel sendiri)
    public function index()
    {
        $users = User::whereIn('role', ['admin', 'rw', 'rt'])->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    // Tambah User Baru (Admin/RW/RT)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'username' => 'required|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,rw,rt',
            'rt' => 'nullable|required_if:role,rt'
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'rt' => $request->rt
        ]);

        return response()->json(['success' => true, 'message' => 'User berhasil ditambahkan', 'data' => $user]);
    }
    
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->only(['name', 'username', 'role', 'rt']);
        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }
        $user->update($data);
        return response()->json(['success' => true, 'message' => 'User berhasil diupdate']);
    }

    // Hapus User
    public function destroy($id)
    {
        User::destroy($id);
        return response()->json(['success' => true, 'message' => 'User berhasil dihapus']);
    }
}