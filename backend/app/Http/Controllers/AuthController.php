<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
 
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // pastikan akun aktif di database (cek kolom enabled = true)
        $credentials['enabled'] = true;
 
        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Username atau password salah',
            ], 401); // -> tampilkan pesan error di form Login (sesuai desain Figma)
        }
 
        $user = Auth::user();
        $token = $user->createToken('dashboard-token')->plainTextToken;
 
        return response()->json([
            'user'  => $user->only('id', 'username', 'role'),
            'token' => $token,
        ]);
    }

    public function whoAmI(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
        ]);
    }
}