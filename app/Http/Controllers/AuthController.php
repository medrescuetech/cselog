<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $identifier = trim($data['login']);
        $field = str_contains($identifier, '@') ? 'email' : 'username';

        $user = User::query()
            ->where($field, $identifier)
            ->where('active', true)
            ->first();

        if (! $user || ! Auth::attempt([
            'id' => $user->id,
            'password' => $data['password'],
            'active' => true,
        ], $request->boolean('remember'))) {
            return back()
                ->withErrors(['login' => 'Those details did not match.'])
                ->onlyInput('login');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('board'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
