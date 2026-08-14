<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogService $logs)
    {
    }

    public function login(LoginRequest $request)
    {
        $user = $this->resolveUser($request->identifier);

        if ($user === null || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Email, nama, atau NIS salah, atau password tidak cocok.'],
            ]);
        }

        $token = $user->createToken($request->device_name ?? 'mobile');

        $this->logs->record($user, 'auth.login', "Login sebagai {$user->role}.", null, $request);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    private function resolveUser(string $identifier): ?User
    {
        $identifier = trim($identifier);

        $user = User::where('email', $identifier)->first();
        if ($user !== null) {
            return $user;
        }

        $user = User::where('name', $identifier)->first();
        if ($user !== null) {
            return $user;
        }

        return User::whereHas('student', fn ($q) => $q->where('nis', $identifier))->first();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        $this->logs->record($request->user(), 'auth.logout', 'Logout.');

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
        ];
    }
}