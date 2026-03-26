<?php

declare(strict_types=1);

namespace Controllers;

use Services\AuthService;

final class DashboardController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function index(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'dashboard', 'dashboard/pages/index', [
            'title' => e('Dashboard'),
            'page_kicker' => e('Overview'),
            'page_title' => e('Dashboard'),
            'page_description' => e('Track uploads, monitor delivery performance, and review the most recent file activity at a glance.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
        ]);
    }

    public function leads(): void
    {
        $user = $this->guard();
        $leadData = $this->uploadedLeadData();

        $this->renderAdminPage($user, 'leads', 'dashboard/pages/leads', [
            'title' => e('Leads'),
            'page_kicker' => e('Lead workspace'),
            'page_title' => e('Leads'),
            'page_description' => e('Upload lead files, review uploaded lead rows, and continue through the mapping flow with the same dataset.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
            'page_action' => $this->uploadButtonHtml(),
            'lead_rows_json' => $this->json($leadData['rows']),
            'lead_headers_json' => $this->json($leadData['headers']),
            'lead_total_records' => e((string) count($leadData['rows'])),
        ]);
    }

    public function uploadLeadFile(): void
    {
        $this->guard();

        if (!isset($_FILES['lead_file']) || !is_array($_FILES['lead_file'])) {
            flash('Please choose a file before uploading.');
            redirect('/leads');
        }

        $file = $_FILES['lead_file'];
        $fileName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || $fileName === '' || $tmpName === '') {
            flash('File upload failed. Please try again.');
            redirect('/leads');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['xls', 'xlsx', 'csv'];

        if (!in_array($extension, $allowed, true)) {
            flash('Only XLS, XLSX, and CSV files are allowed.');
            redirect('/leads');
        }

        $uploadDir = base_path('uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $storedName = uniqid('lead_', true) . '.' . $extension;
        $destination = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $destination)) {
            flash('Unable to store the uploaded file on the server.');
            redirect('/leads');
        }

        $_SESSION['last_uploaded_lead_file'] = [
            'original_name' => $fileName,
            'stored_name' => $storedName,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'size' => $size,
            'extension' => strtoupper($extension),
        ];
        $_SESSION['uploaded_lead_data'] = $this->parseUploadedLeadData($destination, $extension);

        flash('File uploaded successfully. Review the preview and continue to the next step.', 'success');
        redirect('/leads/mapping');
    }

    public function mapping(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $leadData = $this->uploadedLeadData();

        $this->renderAdminPage($user, 'leads', 'dashboard/pages/mapping', [
            'title' => e('Lead Mapping'),
            'page_kicker' => e('Step 1 of 3'),
            'page_title' => e('Lead Mapping'),
            'page_description' => e('Review the uploaded lead file, confirm the detected columns, and continue to region grouping.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
            'page_action' => '<a href="' . e(app_url('leads')) . '" class="panel-link topbar-action-link">Back to Leads</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_file_time' => e((string) ($upload['uploaded_at'] ?? '')),
            'uploaded_file_type' => e((string) ($upload['extension'] ?? '')),
            'uploaded_file_size' => e(number_format(((int) ($upload['size'] ?? 0)) / 1024, 2) . ' KB'),
            'lead_rows_json' => $this->json($leadData['rows']),
            'lead_headers_json' => $this->json($leadData['headers']),
            'mapping_step' => 'preview',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ]);
    }

    public function mappingRegion(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $leadData = $this->uploadedLeadData();
        $regionRows = $this->regionRows($leadData['rows']);
        $regionHeaders = $this->regionHeaders($leadData['headers']);

        $this->renderAdminPage($user, 'leads', 'dashboard/pages/mapping-region', [
            'title' => e('Region Mapping'),
            'page_kicker' => e('Step 2 of 3'),
            'page_title' => e('Region Grouping'),
            'page_description' => e('Review region-wise grouping for the uploaded leads before assigning colleagues.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'region_rows_json' => $this->json($regionRows),
            'region_headers_json' => $this->json($regionHeaders),
            'region_summary_json' => $this->json($this->summarizeRegions($regionRows)),
            'mapping_step' => 'region',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ]);
    }

    public function mappingApiColleagues(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $leadData = $this->uploadedLeadData();

        $this->renderAdminPage($user, 'leads', 'dashboard/pages/mapping-api-colleagues', [
            'title' => e('Assign Colleagues'),
            'page_kicker' => e('Step 3 of 3'),
            'page_title' => e('Assign Region Colleagues'),
            'page_description' => e('Select a colleague for each detected region from the uploaded lead file.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'region_summary_json' => $this->json($this->summarizeRegions($this->regionRows($leadData['rows']))),
            'mapping_step' => 'assign',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ]);
    }

    public function apiSettings(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'api-settings', 'dashboard/pages/api-settings', [
            'title' => e('API Settings'),
            'page_kicker' => e('Integration setup'),
            'page_title' => e('API Settings'),
            'page_description' => e('Configure college endpoints, payload formats, throttling rules, and delivery settings from this page.'),
            'flash_alert' => '',
        ]);
    }

    public function systemConfig(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'system-config', 'dashboard/pages/system-config', [
            'title' => e('System Config'),
            'page_kicker' => e('Platform controls'),
            'page_title' => e('System Config'),
            'page_description' => e('Use this page for environment-level settings, database options, upload rules, and system-wide controls.'),
            'flash_alert' => '',
        ]);
    }

    private function renderAdminPage(array $user, string $activePage, string $contentTemplate, array $overrides = []): void
    {
        $layoutData = $this->layoutData($user, $activePage, $overrides);
        $layoutData['page_content'] = render_template($contentTemplate, $layoutData);
        view('dashboard/layout', $layoutData);
    }

    private function guard(): array
    {
        if (!$this->auth->check()) {
            flash('Please login to continue.');
            redirect('/login');
        }

        return $this->auth->user() ?? [];
    }

    private function uploadedFileOrRedirect(): array
    {
        $upload = $_SESSION['last_uploaded_lead_file'] ?? null;

        if (!is_array($upload)) {
            flash('Upload a lead file first to open the mapping flow.');
            redirect('/leads');
        }

        return $upload;
    }

    private function uploadButtonHtml(): string
    {
        return '<form action="' . e(app_url('leads/upload')) . '" method="post" enctype="multipart/form-data" class="upload-form" data-upload-form>'
            . '<input type="hidden" name="parsed_rows_json" value="" data-upload-rows-json>'
            . '<input type="hidden" name="parsed_headers_json" value="" data-upload-headers-json>'
            . '<label class="panel-link topbar-action-link upload-trigger">Upload Excel File<input type="file" name="lead_file" accept=".xls,.xlsx,.csv" class="d-none" data-upload-input></label>'
            . '</form>';
    }

    private function uploadedLeadData(): array
    {
        $leadData = $_SESSION['uploaded_lead_data'] ?? null;

        if (!is_array($leadData)) {
            return [
                'rows' => [],
                'headers' => [],
            ];
        }

        $rows = isset($leadData['rows']) && is_array($leadData['rows']) ? $leadData['rows'] : [];
        $headers = isset($leadData['headers']) && is_array($leadData['headers']) ? $leadData['headers'] : [];

        return [
            'rows' => $rows,
            'headers' => $headers,
        ];
    }

    private function parseUploadedLeadData(string $filePath, string $extension): array
    {
        $rows = $this->parsedRowsFromRequest();

        if ($rows === [] && $extension === 'csv') {
            $rows = $this->parseCsvRows($filePath);
        }

        $rows = $this->prepareRows($rows);
        $headers = $this->collectHeaders($rows, $this->parsedHeadersFromRequest());

        return [
            'rows' => $rows,
            'headers' => $headers,
        ];
    }

    private function parsedRowsFromRequest(): array
    {
        $payload = $_POST['parsed_rows_json'] ?? null;

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function parsedHeadersFromRequest(): array
    {
        $payload = $_POST['parsed_headers_json'] ?? null;

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($header): string => trim((string) $header), $decoded), static fn (string $header): bool => $header !== ''));
    }

    private function parseCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return [];
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(static fn ($value): string => trim((string) $value), $data);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = (string) ($data[$index] ?? '');
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function prepareRows(array $rawRows): array
    {
        $prepared = [];

        foreach ($rawRows as $index => $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $row = [];
            foreach ($rawRow as $key => $value) {
                $cleanKey = trim((string) $key);
                if ($cleanKey === '') {
                    continue;
                }

                $row[$cleanKey] = is_scalar($value) || $value === null ? trim((string) $value) : '';
            }

            if (!$this->hasRowContent($row)) {
                continue;
            }

            $region = $this->valueByKeys($row, ['region', 'zone']);
            $state = $this->valueByKeys($row, ['state', 'province']);

            if ($region === '') {
                $region = $this->regionFromState($state);
            }

            if ($this->valueByKeys($row, ['lead_id', 'leadid', 'id']) === '') {
                $row['Lead ID'] = 'LD' . (string) (1001 + $index);
            }

            if ($this->valueByKeys($row, ['region', 'zone']) === '') {
                $row['Region'] = $region;
            }

            $prepared[] = $row;
        }

        return array_values($prepared);
    }

    private function collectHeaders(array $rows, array $requestedHeaders = []): array
    {
        $headers = [];

        foreach ($requestedHeaders as $header) {
            if (!in_array($header, $headers, true)) {
                $headers[] = $header;
            }
        }

        foreach ($rows as $row) {
            foreach (array_keys($row) as $header) {
                if (!in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
            }
        }

        return $headers;
    }

    private function hasRowContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function valueByKeys(array $row, array $keys): string
    {
        foreach ($row as $originalKey => $value) {
            if (in_array($this->normalizeKey((string) $originalKey), $keys, true)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);

        return trim((string) $key, '_');
    }

    private function regionFromState(string $stateValue): string
    {
        $stateName = strtolower(trim($stateValue));
        $south = ['karnataka', 'tamil nadu', 'telangana', 'kerala', 'andhra pradesh'];
        $north = ['delhi', 'rajasthan', 'haryana', 'punjab', 'uttar pradesh', 'uttarakhand', 'himachal pradesh', 'jammu and kashmir'];
        $east = ['west bengal', 'odisha', 'bihar', 'jharkhand', 'assam', 'sikkim'];

        if (in_array($stateName, $south, true)) {
            return 'South';
        }

        if (in_array($stateName, $north, true)) {
            return 'North';
        }

        if (in_array($stateName, $east, true)) {
            return 'East';
        }

        return 'West / Others';
    }

    private function regionRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $region = $this->valueByKeys($row, ['region', 'zone']);

            if ($region === '') {
                $region = $this->regionFromState($this->valueByKeys($row, ['state', 'province']));
            }

            $row['Region'] = $region;

            return $row;
        }, $rows);
    }

    private function regionHeaders(array $headers): array
    {
        if (!in_array('Region', $headers, true)) {
            array_unshift($headers, 'Region');
        }

        return array_values(array_unique($headers));
    }

    private function summarizeRegions(array $rows): array
    {
        $summary = [];

        foreach ($rows as $row) {
            $region = $this->valueByKeys($row, ['region', 'zone']) ?: 'West / Others';
            $summary[$region] = ($summary[$region] ?? 0) + 1;
        }

        $cards = [];
        foreach ($summary as $region => $total) {
            $cards[] = [
                'region' => $region,
                'total' => $total,
            ];
        }

        return $cards;
    }

    private function json(array $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    private function layoutData(array $user, string $activePage, array $overrides = []): array
    {
        $base = [
            'app_name' => e(env('APP_NAME', 'Leads API Project')),
            'logout_action' => e(app_url('logout')),
            'css_url' => e(asset('css/app.css')),
            'js_url' => e(asset('js/app.js')),
            'user_email' => e((string) ($user['email'] ?? '')),
            'logged_in_at' => e((string) ($user['logged_in_at'] ?? '-')),
            'dashboard_url' => e(app_url('dashboard')),
            'leads_url' => e(app_url('leads')),
            'api_settings_url' => e(app_url('api-settings')),
            'system_config_url' => e(app_url('system-config')),
            'dashboard_active' => $activePage === 'dashboard' ? 'is-active' : '',
            'leads_active' => $activePage === 'leads' ? 'is-active' : '',
            'api_settings_active' => $activePage === 'api-settings' ? 'is-active' : '',
            'system_config_active' => $activePage === 'system-config' ? 'is-active' : '',
            'flash_alert' => '',
            'page_action' => '',
            'uploaded_file_name' => '',
            'uploaded_file_time' => '',
            'uploaded_file_type' => '',
            'uploaded_file_size' => '',
            'mapping_step' => '',
            'lead_rows_json' => '[]',
            'lead_headers_json' => '[]',
            'lead_total_records' => '0',
            'region_rows_json' => '[]',
            'region_headers_json' => '[]',
            'region_summary_json' => '[]',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ];

        return array_merge($base, $overrides);
    }
}
