<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:business,individual',
            'business_name' => 'required_if:role,business|nullable|string',
            'phone' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'id' => (string) Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'business_name' => $request->business_name,
                'phone' => $request->phone,
                'email_verification_token' => Str::random(60)
            ]);

            // Create default categories
            $this->createDefaultCategories($user->id);

            AuditLog::log($user->id, 'register', 'users', $user->id, null, $user->toArray());

            DB::commit();

            return response()->json([
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user_id' => $user->id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    private function createDefaultCategories($userId)
    {
        $categories = [
            ['name' => 'Sales', 'type' => 'income', 'color' => '#10b981'],
            ['name' => 'Services', 'type' => 'income', 'color' => '#3b82f6'],
            ['name' => 'Rent', 'type' => 'expense', 'color' => '#ef4444'],
            ['name' => 'Salaries', 'type' => 'expense', 'color' => '#f59e0b'],
            ['name' => 'Utilities', 'type' => 'expense', 'color' => '#8b5cf6'],
            ['name' => 'Marketing', 'type' => 'expense', 'color' => '#ec489a'],
            ['name' => 'Supplies', 'type' => 'expense', 'color' => '#14b8a6'],
            ['name' => 'Transport', 'type' => 'expense', 'color' => '#f97316'],
            ['name' => 'Food', 'type' => 'expense', 'color' => '#eab308'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#d946ef']
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'name' => $category['name'],
                'type' => $category['type'],
                'color' => $category['color'],
                'is_system' => true
            ]);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();

        if (!$user->email_verified_at) {
            return response()->json(['error' => 'Please verify your email before logging in'], 403);
        }

        AuditLog::log($user->id, 'login', 'users', $user->id, null, ['ip' => $request->ip()]);

        return $this->respondWithToken($token, $user);
    }

    public function logout()
    {
        $user = auth()->user();
        AuditLog::log($user->id, 'logout', 'users', $user->id);
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh(), auth()->user());
    }

    public function profile()
    {
        return response()->json(auth()->user());
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string',
            'business_name' => 'nullable|string'
        ]);

        $user->update($request->only(['name', 'phone', 'business_name']));
        
        AuditLog::log($user->id, 'update_profile', 'users', $user->id, null, $user->toArray());

        return response()->json($user);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 401);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        AuditLog::log($user->id, 'change_password', 'users', $user->id);

        return response()->json(['message' => 'Password changed successfully']);
    }

    private function respondWithToken($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'business_name' => $user->business_name
            ]
        ]);
    }
}