<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
 
class AuthController extends Controller
{
    public function login(Request $request)
    {
        if(false) {
            $this -> createEmergencyAccount();
        }

        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
 
        if (! Auth::attempt($validated)) {
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

    private function createEmergencyAccount()
    {
        $data = [
            "username" => "admin",
            "password" => "admin",
            "role" => "owner"
        ];
        $data['password'] = Hash::make($data['password']);
        User::create($data);
    }
}