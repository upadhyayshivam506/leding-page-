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

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '') ?: '';

    if ($scriptName !== '/' && str_starts_with($path, $scriptName)) {
        $path = substr($path, strlen($scriptName)) ?: '/';
    }

    return '/' . ltrim($path, '/');
}

function current_page_number(string $queryKey = 'page'): int
{
    $page = filter_input(INPUT_GET, $queryKey, FILTER_VALIDATE_INT);

    return max(1, (int) ($page ?: 1));
}

function pagination_state(int $totalRecords, int $recordsPerPage, int $currentPage): array
{
    $recordsPerPage = max(1, $recordsPerPage);
    $totalRecords = max(0, $totalRecords);
    $totalPages = max(1, (int) ceil($totalRecords / $recordsPerPage));
    $currentPage = min(max(1, $currentPage), $totalPages);
    $offset = ($currentPage - 1) * $recordsPerPage;

    return [
        'total_records' => $totalRecords,
        'records_per_page' => $recordsPerPage,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'from_record' => $totalRecords > 0 ? $offset + 1 : 0,
        'to_record' => $totalRecords > 0 ? min($offset + $recordsPerPage, $totalRecords) : 0,
    ];
}

function paginate_array(array $rows, int $currentPage, int $recordsPerPage): array
{
    $state = pagination_state(count($rows), $recordsPerPage, $currentPage);
    $state['rows'] = array_slice(array_values($rows), $state['offset'], $state['records_per_page']);

    return $state;
}

function render_table_head(array $headers): string
{
    if ($headers === []) {
        return '';
    }

    return '<tr>' . implode('', array_map(
        static fn (string $header): string => '<th>' . e($header) . '</th>',
        $headers
    )) . '</tr>';
}

function render_table_body(array $headers, array $rows, string $emptyMessage = 'No data available.'): string
{
    if ($rows === [] || $headers === []) {
        return '<tr><td colspan="' . max(1, count($headers)) . '" class="table-empty-state">' . e($emptyMessage) . '</td></tr>';
    }

    return implode('', array_map(static function (array $row) use ($headers): string {
        $cells = array_map(static function (string $header) use ($row): string {
            return '<td>' . e((string) ($row[$header] ?? '')) . '</td>';
        }, $headers);

        return '<tr>' . implode('', $cells) . '</tr>';
    }, $rows));
}

function generatePagination(int $current_page, int $total_pages, string $basePath = '', string $queryKey = 'page'): string
{
    if ($total_pages <= 1) {
        return '';
    }

    $maxVisiblePages = 5;
    $pages = pagination_window($current_page, $total_pages, $maxVisiblePages);
    $markup = [
        '<nav class="table-pagination" aria-label="Pagination">',
        '<div class="table-pagination__inner">',
        pagination_button('Prev', max(1, $current_page - 1), $current_page > 1, $basePath, $queryKey, false, 'Go to previous page'),
    ];

    foreach ($pages as $page) {
        $markup[] = pagination_button((string) $page, $page, true, $basePath, $queryKey, $page === $current_page, 'Go to page ' . $page);
    }

    $lastVisiblePage = (int) end($pages);
    if ($lastVisiblePage < $total_pages) {
        if ($lastVisiblePage < $total_pages - 1) {
            $markup[] = '<span class="table-page-ellipsis" aria-hidden="true">...</span>';
        }

        $markup[] = pagination_button((string) $total_pages, $total_pages, true, $basePath, $queryKey, $total_pages === $current_page, 'Go to page ' . $total_pages);
    }

    $markup[] = pagination_button('Last', $total_pages, $current_page < $total_pages, $basePath, $queryKey, false, 'Go to last page');
    $markup[] = '</div>';
    $markup[] = '</nav>';

    return implode('', $markup);
}

function pagination_window(int $currentPage, int $totalPages, int $maxVisiblePages = 5): array
{
    if ($totalPages <= $maxVisiblePages) {
        return range(1, $totalPages);
    }

    if ($currentPage <= 4) {
        return range(1, $maxVisiblePages);
    }

    if ($currentPage >= $totalPages - 3) {
        return range($totalPages - $maxVisiblePages + 1, $totalPages);
    }

    return range($currentPage - 2, $currentPage + 2);
}

function pagination_button(
    string $label,
    int $page,
    bool $isEnabled,
    string $basePath,
    string $queryKey,
    bool $isActive,
    string $ariaLabel
): string {
    $classes = 'table-page-btn';
    if ($isActive) {
        $classes .= ' is-active';
    }

    if ($isActive) {
        return '<span class="' . $classes . '" aria-current="page">' . e($label) . '</span>';
    }

    if (!$isEnabled) {
        return '<span class="' . $classes . ' is-disabled" aria-disabled="true">' . e($label) . '</span>';
    }

    return '<a href="' . e(pagination_url($page, $basePath, $queryKey)) . '" class="' . $classes . '" aria-label="' . e($ariaLabel) . '">' . e($label) . '</a>';
}

function pagination_url(int $page, string $basePath = '', string $queryKey = 'page'): string
{
    $query = $_GET;
    unset($query[$queryKey]);
    $query[$queryKey] = max(1, $page);

    $path = $basePath !== '' ? app_url($basePath) : app_url(ltrim(request_path(), '/'));
    $queryString = http_build_query($query);

    return $path . ($queryString !== '' ? '?' . $queryString : '');
}
