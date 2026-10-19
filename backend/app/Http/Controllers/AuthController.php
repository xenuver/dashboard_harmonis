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
        ], [
            'username.required' => 'Nama pengguna harus diisi',
            'password.required' => 'Password harus diisi',
            'username.string' => 'Nama pengguna harus merupakan string',
            'password.string' => 'Password harus merupakan string',
        ]);

        // bagian untuk menambahkan pemeriksaan apakah akun aktif (enabled = true)
        // dalam database. jika field 'enabled' dalam database dari user tidak true, 
        // maka autentikasi akan ditolak walaupun username dan password sesuai.
        $credentials['enabled'] = true;
 
        if (! Auth::attempt($credentials, false)) {
            return response()->json([
                'message' => 'Username atau password salah',
            ], 401); // -> tampilkan pesan error di form Login (sesuai desain Figma)
        }
 
        $user = $request->user();
        $token = $user->createToken('auth_token', ['*'], now()->addHour())->plainTextToken;
 
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

    public function logOut(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated or invalid token'], 401);
        }

        $currentToken = $request->user()->currentAccessToken();

        if (method_exists($currentToken, 'delete')) {
            $currentToken->update([
                'expires_at' => now()->subSecond(),
            ]);
        } else {
            Auth::guard('web')->logout(); // SPA Cookie Session
        }

        return response()->json([
            'message'       => "anda telah keluar",
        ]);
    }
}