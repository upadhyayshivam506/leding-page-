<?php

declare(strict_types=1);

namespace Controllers;

use Models\Lead;
use PDOException;
use RuntimeException;
use Services\AuthService;

final class DashboardController
{
    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly Lead $leads = new Lead()
    )
    {
    }

    public function index(): void
    {
        $user = $this->guard();
        $stats = $this->leadStats();
        $this->renderAdminPage($user, 'dashboard', 'dashboard/pages/index', [
            'title' => e('Dashboard'),
            'page_kicker' => e('Overview'),
            'page_title' => e('Dashboard'),
            'page_description' => e('Track uploaded lead data, monitor totals, and review the most recent file activity at a glance.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
            'total_students' => e((string) $stats['total_leads']),
            'total_uploaded_files' => e((string) $stats['total_uploaded_files']),
        ]);
    }

    public function leads(): void
    {
        $user = $this->guard();
        $leadData = $this->leadDataForView();

        $this->renderAdminPage($user, 'leads', 'leads/pages/leads', [
            'title' => e('Leads'),
            'page_kicker' => e('Lead workspace'),
            'page_title' => e('Leads'),
            'page_description' => e('Upload lead files, save mapped rows into MySQL, and review the stored leads from the database.'),
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
        if (!in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
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

        try {
            $leadData = $this->parseUploadedLeadData($destination, $extension);
            $batchId = uniqid('batch_', true);
            $inserted = $this->leads->insertMany($this->databaseRows($leadData['rows']), $batchId, $fileName);
        } catch (RuntimeException|PDOException $exception) {
            flash($exception->getMessage() !== '' ? $exception->getMessage() : 'Lead upload failed.');
            redirect('/leads');
        }

        $_SESSION['last_uploaded_lead_file'] = [
            'original_name' => $fileName,
            'stored_name' => $storedName,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'size' => $size,
            'extension' => strtoupper($extension),
            'batch_id' => $batchId,
        ];
        $_SESSION['uploaded_lead_data'] = $leadData;

        flash('File uploaded successfully. ' . $inserted . ' leads were saved with mapped regions.', 'success');
        redirect('/leads/mapping');
    }

    public function mapping(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $leadData = $this->leadDataForBatch((string) ($upload['batch_id'] ?? ''));

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping', [
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
        $batchId = (string) ($upload['batch_id'] ?? '');
        $rows = $this->leadDataForBatch($batchId)['rows'];
        $summary = $this->leads->summarizeByBatch($batchId);

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-region', [
            'title' => e('Region Mapping'),
            'page_kicker' => e('Step 2 of 3'),
            'page_title' => e('Region Grouping'),
            'page_description' => e('Review region-wise grouping for the uploaded leads before assigning colleagues.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'region_rows_attr_json' => $this->jsonAttribute($rows),
            'region_summary_attr_json' => $this->jsonAttribute($summary),
            'uploaded_batch_id' => e($batchId),
            'mapping_step' => 'region',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ]);
    }

    public function mappingApiColleagues(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $batchId = (string) ($upload['batch_id'] ?? '');
        $summary = $this->requestedRegionSummary() ?: $this->leads->summarizeByBatch($batchId);

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-api-colleagues', [
            'title' => e('Assign Colleagues'),
            'page_kicker' => e('Step 3 of 3'),
            'page_title' => e('Assign Region Colleagues'),
            'page_description' => e('Select one or more colleagues from the dropdown and push all leads to the selected APIs.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_batch_id' => e($batchId),
            'region_summary_attr_json' => $this->jsonAttribute($summary),
            'fetch_colleagues_url' => e(app_url('api/fetch-colleagues.php')),
            'assign_colleagues_url' => e(app_url('api/push-leads.php')),
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

        if (!is_array($upload) || trim((string) ($upload['batch_id'] ?? '')) === '') {
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

    private function leadDataForView(): array
    {
        try {
            $rows = $this->leads->all();
        } catch (PDOException) {
            return $this->uploadedLeadData();
        }

        if ($rows === []) {
            return $this->uploadedLeadData();
        }

        return [
            'rows' => array_map(static function (array $row): array {
                return [
                    'Batch ID' => (string) ($row['batch_id'] ?? ''),
                    'Lead ID' => (string) ($row['lead_id'] ?? ''),
                    'Name' => (string) ($row['name'] ?? ''),
                    'Email' => (string) ($row['email'] ?? ''),
                    'Phone' => (string) ($row['phone'] ?? ''),
                    'Course' => (string) ($row['course'] ?? ''),
                    'Specialization' => (string) ($row['specialization'] ?? ''),
                    'Campus' => (string) ($row['campus'] ?? ''),
                    'College Name' => (string) ($row['college_name'] ?? ''),
                    'City' => (string) ($row['city'] ?? ''),
                    'State' => (string) ($row['state'] ?? ''),
                    'Region' => (string) ($row['region'] ?? ''),
                    'Source File' => (string) ($row['source_file'] ?? ''),
                    'Created At' => (string) ($row['created_at'] ?? ''),
                ];
            }, $rows),
            'headers' => ['Batch ID', 'Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region', 'Source File', 'Created At'],
        ];
    }

    private function leadDataForBatch(string $batchId): array
    {
        $rows = [];

        if ($batchId !== '') {
            try {
                $rows = $this->leads->findByBatch($batchId);
            } catch (PDOException) {
                $rows = [];
            }
        }

        if ($rows === []) {
            return $this->uploadedLeadData();
        }

        return [
            'rows' => array_map(static function (array $row): array {
                return [
                    'Lead ID' => (string) ($row['lead_id'] ?? ''),
                    'Name' => (string) ($row['name'] ?? ''),
                    'Email' => (string) ($row['email'] ?? ''),
                    'Phone' => (string) ($row['phone'] ?? ''),
                    'Course' => (string) ($row['course'] ?? ''),
                    'Specialization' => (string) ($row['specialization'] ?? ''),
                    'Campus' => (string) ($row['campus'] ?? ''),
                    'College Name' => (string) ($row['college_name'] ?? ''),
                    'City' => (string) ($row['city'] ?? ''),
                    'State' => (string) ($row['state'] ?? ''),
                    'Region' => (string) ($row['region'] ?? ''),
                ];
            }, $rows),
            'headers' => ['Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region'],
        ];
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

        return [
            'rows' => isset($leadData['rows']) && is_array($leadData['rows']) ? $leadData['rows'] : [],
            'headers' => isset($leadData['headers']) && is_array($leadData['headers']) ? $leadData['headers'] : [],
        ];
    }

    private function parseUploadedLeadData(string $filePath, string $extension): array
    {
        $rows = $this->parseSpreadsheetRows($filePath, $extension);

        if ($rows === []) {
            $rows = $this->parsedRowsFromRequest();
        }

        $rows = $this->prepareRows($rows);
        $headers = ['Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region'];

        return [
            'rows' => $rows,
            'headers' => $headers,
        ];
    }

    private function parseSpreadsheetRows(string $filePath, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->parseCsvRows($filePath);
        }

        $autoload = base_path('vendor/autoload.php');
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return [];
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray('', true, true, false);
        if ($sheetRows === []) {
            return [];
        }

        $headerRow = array_shift($sheetRows);
        $headers = array_map(static fn ($value): string => trim((string) $value), is_array($headerRow) ? $headerRow : []);
        $rows = [];

        foreach ($sheetRows as $sheetRow) {
            if (!is_array($sheetRow)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($sheetRow[$index] ?? ''));
            }

            $rows[] = $row;
        }

        return $rows;
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

    private function parseCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the uploaded CSV file.');
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

                $row[$header] = trim((string) ($data[$index] ?? ''));
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

            $row = [
                'Lead ID' => $this->firstValue($rawRow, ['lead_id', 'leadid', 'id']) ?: 'LD' . (string) (1001 + $index),
                'Name' => $this->firstValue($rawRow, ['name', 'student_name', 'full_name']),
                'Email' => $this->firstValue($rawRow, ['email', 'email_address']),
                'Phone' => $this->firstValue($rawRow, ['phone', 'mobile', 'mobile_number', 'phone_number', 'contact']),
                'Course' => $this->firstValue($rawRow, ['course', 'program']),
                'Specialization' => $this->firstValue($rawRow, ['specialization']),
                'Campus' => $this->firstValue($rawRow, ['campus', 'college_campus']),
                'College Name' => $this->firstValue($rawRow, ['college_name', 'college']),
                'City' => $this->firstValue($rawRow, ['city']),
                'State' => $this->firstValue($rawRow, ['state', 'province']),
            ];

            if (!$this->hasRowContent($row)) {
                continue;
            }

            $row['Region'] = getRegionByState($row['State']);
            $prepared[] = $row;
        }

        if ($prepared === []) {
            throw new RuntimeException('No lead rows were found in the uploaded file.');
        }

        return $prepared;
    }

    private function hasRowContent(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === 'Lead ID') {
                continue;
            }

            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function firstValue(array $row, array $keys): string
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

    private function databaseRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            return [
                'lead_id' => (string) ($row['Lead ID'] ?? ''),
                'name' => (string) ($row['Name'] ?? ''),
                'email' => (string) ($row['Email'] ?? ''),
                'phone' => (string) ($row['Phone'] ?? ''),
                'course' => (string) ($row['Course'] ?? ''),
                'specialization' => (string) ($row['Specialization'] ?? ''),
                'campus' => (string) ($row['Campus'] ?? ''),
                'college_name' => (string) ($row['College Name'] ?? ''),
                'city' => (string) ($row['City'] ?? ''),
                'state' => (string) ($row['State'] ?? ''),
                'region' => (string) ($row['Region'] ?? 'West / Others'),
            ];
        }, $rows);
    }

    private function requestedRegionSummary(): array
    {
        $payload = $_GET['region_summary_json'] ?? null;
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function json(array $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    private function jsonAttribute(array $value): string
    {
        return e($this->json($value));
    }

    private function leadStats(): array
    {
        try {
            return [
                'total_leads' => $this->leads->countAll(),
                'total_uploaded_files' => $this->leads->countDistinctBatches(),
            ];
        } catch (PDOException) {
            return [
                'total_leads' => 0,
                'total_uploaded_files' => 0,
            ];
        }
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
            'uploaded_batch_id' => '',
            'total_students' => '0',
            'total_uploaded_files' => '0',
            'mapping_step' => '',
            'lead_rows_json' => '[]',
            'lead_headers_json' => '[]',
            'lead_total_records' => '0',
            'region_rows_attr_json' => '[]',
            'region_summary_attr_json' => '[]',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
            'fetch_colleagues_url' => e(app_url('api/fetch-colleagues.php')),
            'assign_colleagues_url' => e(app_url('api/push-leads.php')),
        ];

        return array_merge($base, $overrides);
    }
}



