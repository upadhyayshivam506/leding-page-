<?php

declare(strict_types=1);

namespace Controllers;

use Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function showLogin(): void
    {
        if ($this->auth->check()) {
            redirect('/dashboard');
        }

        view('auth/login', [
            'title' => e('Admin Login'),
            'app_name' => e(env('APP_NAME', 'Leads API Project')),
            'login_action' => e(app_url('login')),
            'css_url' => e(asset('css/app.css')),
            'js_url' => e(asset('js/app.js')),
            'illustration_url' => e(asset('images/login-portal-illustration.svg')),
            'email_value' => e(old('email')),
            'flash_alert' => render_alert(flash(), 'auth-alert'),
        ]);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        remember_old(['email' => $email]);

        if ($email === '' || $password === '') {
            flash('Please enter both email and password.');
            redirect('/login');
        }

        if (!$this->auth->attempt($email, $password)) {
            flash('Invalid admin credentials. Please try again.');
            redirect('/login');
        }

        clear_old();
        session_regenerate_id(true);
        $this->auth->login($email);
        flash('Welcome back. You are now signed in.', 'success');

        redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->auth->logout();
        flash('You have been logged out.', 'success');
        redirect('/login');
    }
}
