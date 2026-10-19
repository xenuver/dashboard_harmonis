<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
 
class ManageUsersController extends Controller
{
    public function listUsers(Request $request)
    {
        $request->mergeIfMissing([
            'per_page' => 10,
            'page' => 1,
        ]);

        $validated = $request->validate([
            'usernamelike' => 'sometimes|string|alpha_dash:ascii',
            'role' => 'sometimes|in:it_staff,owner',
            'per_page' => 'required|integer|in:10,20,50',
            'page' => 'required|integer|min:1'
        ], [
            'usernamelike.string' => 'format kata kunci nama pengguna harus merupakan string',
            'usernamelike.alpha_dash' => 'kata kunci nama pengguna hanya menerima huruf dan angka',
            'role.in' => 'Role dari pengguna hanya menerima nilai it_staff dan owner',
            'per_page.required' => 'data per halaman harus diisi',
            'per_page.integer' => 'format data per halaman harus merupakan angka dalam integer',
            'per_page.in' => 'jumlah data per halaman hanya menerima nilai 10, 20, dan 50',
            'page.required' => 'halaman harus diisi',
            'page.integer' => 'halaman harus merupakan angka dalam integer',
            'page.min' => 'angka pada halaman harus minimal 1',
        ]);

        $query = User::query();

        if(! empty($validated['usernamelike'])) {
            $term = addcslashes($validated['usernamelike'], '\\%_');
            $query->where('username', 'like', '%'.$term.'%');
        }

        if(! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        return $query->orderBy('username')->paginate($validated['per_page'], ['id', 'username', 'role', 'enabled']);
    }

    public function addUser(Request $request)
    {
        $request->mergeIfMissing([
            'role' => 'owner',
            'enabled' => true,
        ]);

        $validated = $request->validate([
            'username' => 'required|string|alpha_dash:ascii|unique:users,username',
            'password' => 'required|string',
            'role' => 'required|in:it_staff,owner',
            'enabled' => 'required|boolean',
        ]);

        $newUser = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'enabled' => $validated['enabled'],
            'role' => $validated['role']
        ]);
 
        return response()->json([
            'message' => 'Pengguna berhasil dibuat',
            'user' => $newUser->only(['id', 'username', 'role', 'enabled'],)
        ]);
    }

    public function updateUser(Request $request)
    {
        $validated = $request->validate([
            'userid' => 'required|exists:users,id',
            'username' => 'sometimes|string|alpha_dash:ascii',
            'password' => 'sometimes|string',
            'role' => 'sometimes|in:it_staff,owner',
            'enabled' => 'sometimes|boolean',
        ]);

        $user = User::findOrFail($validated['userid']);

        if(array_key_exists('username', $validated)) {
            if(User::where('username', $validated['username'])->where('id', '!=', $user->id)->exists()) {
                return response()->json(['message' => 'Nama pengguna sudah digunakan'], 409);
            }

            $user->username = $validated['username'];
        }

        if(array_key_exists('password', $validated)) {
            $user->password = Hash::make($validated['password']);
        }

        if (array_key_exists('enabled', $validated)) {
            if ($request->user()->id === $validated['userid'] && ($validated['enabled'] === false)) {
                return response()->json(['message' => 'Tidak dapat menonaktifkan akun sendiri'], 422);
            }

            if ($request->user()->id === $validated['userid'] && ($validated['enabled'] === true)) {
                return response()->json(['message' => 'Tidak dapat mengaktifkan akun sendiri'], 422);
            }

            $user->enabled = $validated['enabled'];
        }

        if(array_key_exists('role', $validated)) {
            if($request->user()->id === $validated['userid'] && $validated['role'] === 'owner') {
                return response()->json(['message' => 'Tidak dapat menurunkan hak akses akun sendiri'], 422);
            }
            $user->role = $validated['role'];
        }

        $user->save();

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui',
            'user' => $user->only(['id', 'username', 'role', 'enabled']),
        ]);
    }

    public function deleteUser(Request $request)
    {
        $validated = $request->validate([
            'userid' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['userid']);

        if ($request->user()->id === $validated['userid']) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri'], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->update([
                'expires_at' => now()->subSecond(),
            ]);
            $user->delete();
        });

        return response()->json([
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }
}