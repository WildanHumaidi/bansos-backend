<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,rw,rt,warga',   
            'rt'       => 'nullable|string',
            'alamat'   => 'nullable|string',                  
        ]);

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'rt'       => $request->rt,
            'alamat'   => $request->alamat ?? '',           
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan',
            'data'    => $user,
        ], 201);
    }

    /**
     * Update data user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'     => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $id,
            'password' => 'nullable|string|min:6',
            'role'     => 'sometimes|in:admin,rw,rt,warga',
            'rt'       => 'nullable|string',
            'alamat'   => 'nullable|string',
        ]);

        $data = $request->only(['name', 'username', 'role', 'rt', 'alamat']);

        
        if (array_key_exists('alamat', $data) && is_null($data['alamat'])) {
            $data['alamat'] = '';
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate',
            'data'    => $user,
        ]);
    }

    /**
     * Hapus User.
     */
    public function destroy($id)
    {
        User::destroy($id);
        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus',
        ]);
    }
}