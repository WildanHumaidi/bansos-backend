<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // ==========================================
    // 1. REGISTRASI WARGA
    // ==========================================
    public function register(Request $request)
    {
        try {
            $request->validate([
                'nama_lengkap' => 'required|string|max:255',
                'username'     => 'required|string|unique:users',
                'password'     => 'required|min:6',
                'rt'           => 'required',
                'alamat'       => 'required|string', 
            ]);

            $user = User::create([
                'name'     => $request->nama_lengkap, 
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role'     => 'warga', 
                'rt'       => $request->rt,
                'alamat'   => $request->alamat, 
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registrasi Berhasil',
                'user'    => $user,
                'token'   => $token
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. LOGIN SISTEM
    // ==========================================
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            if (!Auth::attempt($request->only('username', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau Password salah!'
                ], 401);
            }

            $user = User::where('username', $request->username)->firstOrFail();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login Berhasil',
                'user' => $user,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 3. LOGOUT
    // ==========================================
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'success' => true, 
                'message' => 'Logout berhasil'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}