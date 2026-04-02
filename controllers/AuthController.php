<?php

declare(strict_types=1);

namespace Controllers;

use PDOException;
use Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function showLogin(): void
    {
        $this->auth->initialize();

        if ($this->auth->check()) {
            redirect('/dashboard');
        }

        view('auth/login', [
            'title' => e('Admin Login'),
            'app_name' => e(env('APP_NAME', 'Leads API Project')),
            'login_action' => e(app_url('login')),
            'css_url' => e(asset('css/app.css')),
            'js_url' => e(asset('js/app.js')),
            'csrf_token' => e(csrf_token()),
            'csrf_field' => csrf_field(),
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

        $user = $this->auth->authenticate($email, $password);
        if ($user === null) {
            flash('Invalid admin credentials. Please try again.');
            redirect('/login');
        }

        clear_old();
        $this->auth->login($user);
        flash('Welcome back. You are now signed in.', 'success');

        redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->auth->logout();
        flash('You have been logged out.', 'success');
        redirect('/login');
    }

    public function changePassword(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            flash('Please login to continue.');
            redirect('/login');
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $errors = $this->validatePasswordChangeInput($currentPassword, $newPassword, $confirmPassword);
        if ($errors !== []) {
            validation_errors($errors);
            flash('Password update failed. Please try again.');
            redirect('/system-config');
        }

        try {
            $updated = $this->auth->updatePassword((int) ($user['id'] ?? 0), $currentPassword, $newPassword);
        } catch (PDOException) {
            validation_errors([]);
            flash('Password update failed. Please try again.');
            redirect('/system-config');
        }

        if (!$updated) {
            validation_errors([
                'current_password' => 'Current password is incorrect.',
            ]);
            flash('Current password is incorrect.');
            redirect('/system-config');
        }

        validation_errors([]);
        $this->auth->logout();
        flash('Your password has been successfully changed.', 'success');
        redirect('/login');
    }

    private function validatePasswordChangeInput(string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        $errors = [];

        if (trim($currentPassword) === '') {
            $errors['current_password'] = 'Current password is required.';
        }

        if ($newPassword === '') {
            $errors['new_password'] = 'New password is required.';
        } elseif (!$this->isStrongPassword($newPassword)) {
            $errors['new_password'] = 'Use at least 8 characters with uppercase, lowercase, and a number.';
        }

        if ($confirmPassword === '') {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== '' && !hash_equals($newPassword, $confirmPassword)) {
            $errors['confirm_password'] = 'New password and confirm password must match.';
        }

        return $errors;
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }
}
