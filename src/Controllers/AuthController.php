<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Auth;
use CseLog\Db;
use CseLog\Http;
use CseLog\Throttle;

final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::user() !== null) {
            Http::redirect('/board');
        }

        $noUsers = (int) Db::value('SELECT COUNT(*) FROM users') === 0;

        Http::render('login', ['title' => 'Sign in', 'bare' => true, 'noUsers' => $noUsers]);
    }

    public function login(): void
    {
        Http::verifyCsrf();

        $username = (string) Http::input('username', '');

        if (!Auth::attempt($username, (string) Http::input('password', ''))) {
            $wait = Throttle::retryInSeconds($username);

            Http::flash($wait > 0
                ? 'Too many failed attempts. Try again in ' . (int) ceil($wait / 60) . ' minutes.'
                : 'Wrong username or password.', 'error');
            Http::redirect('/login');
        }

        $intended = $_SESSION['intended'] ?? '/board';
        unset($_SESSION['intended']);

        Http::redirect(is_string($intended) ? $intended : '/board');
    }

    public function logout(): void
    {
        Auth::logout();
        Http::redirect('/login');
    }
}
