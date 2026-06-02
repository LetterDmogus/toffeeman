<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class KioskAuthController extends Controller
{
    /**
     * Register a new customer account from kiosk.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', Password::min(6)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $user->assignRole('customer');

        Auth::login($user);

        return response()->json($user->only(['id', 'name', 'email', 'phone']), 201);
    }

    /**
     * Login an existing customer from kiosk.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $validated['login'];

        /** @var \App\Models\User|null $user */
        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || ! $user->hasRole('customer') || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Email/nomor HP atau password salah.'], 401);
        }

        Auth::login($user, remember: true);

        return response()->json($user->only(['id', 'name', 'email', 'phone']));
    }

    /**
     * Logout the current customer session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
