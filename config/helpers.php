<?php

declare(strict_types=1);

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function app_url(string $path = ''): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $baseDir = $baseDir === '.' ? '' : $baseDir;

    return ($baseDir ?: '') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $cleanPath = 'static/' . ltrim($path, '/');
    $url = app_url($cleanPath);
    $fullPath = base_path($cleanPath);

    if (is_file($fullPath)) {
        $url .= '?v=' . filemtime($fullPath);
    }

    return $url;
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function render_alert(?array $flash, string $className): string
{
    if ($flash === null || !isset($flash['message'])) {
        return '';
    }

    $typeClass = ($flash['type'] ?? 'error') === 'success' ? 'success' : 'danger';

    return '<div class="alert alert-' . $typeClass . ' ' . $className . '" role="alert">' . e((string) $flash['message']) . '</div>';
}

function render_template(string $template, array $data = []): string
{
    $htmlTemplate = base_path('Templates/' . $template . '.html');

    if (!is_file($htmlTemplate)) {
        http_response_code(500);
        exit('Template not found.');
    }

    $content = file_get_contents($htmlTemplate);

    if ($content === false) {
        http_response_code(500);
        exit('Unable to read template.');
    }

    $replacements = [];

    foreach ($data as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string) $value;
    }

    return strtr($content, $replacements);
}

function view(string $template, array $data = []): void
{
    echo render_template($template, $data);
}

function flash(?string $message = null, string $type = 'error'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type,
        ];

        return null;
    }

    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function old(string $key, string $default = ''): string
{
    return $_SESSION['old'][$key] ?? $default;
}

function remember_old(array $payload): void
{
    $_SESSION['old'] = $payload;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}
