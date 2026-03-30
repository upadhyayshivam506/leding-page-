<?php

declare(strict_types=1);

namespace Controllers;

use Models\Lead;
use Models\LeadPushLog;
use PDOException;
use RuntimeException;
use Services\AuthService;
use Services\LeadMappingService;

final class DashboardController
{
    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly Lead $leads = new Lead(),
        private readonly LeadPushLog $leadPushLogs = new LeadPushLog(),
        private readonly LeadMappingService $leadMapping = new LeadMappingService()
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

    public function leadPushLogs(): void
    {
        $user = $this->guard();
        $logData = $this->leadPushLogDataForView();
        $logStats = $this->leadPushLogStats();

        $this->renderAdminPage($user, 'lead-push-logs', 'dashboard/pages/lead-push-logs', [
            'title' => e('Lead Push Logs'),
            'page_kicker' => e('Delivery audit'),
            'page_title' => e('Lead Push Logs'),
            'page_description' => e('Review every API push attempt with status, region, college, response, and delivery timestamp.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
            'log_rows_json' => $this->json($logData['rows']),
            'log_headers_json' => $this->json($logData['headers']),
            'log_total_records' => e((string) count($logData['rows'])),
            'log_total_attempts' => e((string) $logStats['total_attempts']),
            'log_success_count' => e((string) $logStats['success_count']),
            'log_failed_count' => e((string) $logStats['failed_count']),
            'log_distinct_colleges' => e((string) $logStats['distinct_colleges']),
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
        $durationDefaults = $this->durationDefaults();

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-region', [
            'title' => e('Region Mapping'),
            'page_kicker' => e('Step 2 of 3'),
            'page_title' => e('Region Grouping'),
            'page_description' => e('Review region grouping, open the course mapping workflow, and prepare preview data without leaving this page.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_file_time' => e((string) ($upload['uploaded_at'] ?? '')),
            'region_rows_attr_json' => $this->jsonAttribute($rows),
            'region_summary_attr_json' => $this->jsonAttribute($summary),
            'available_columns_attr_json' => $this->jsonAttribute($this->leadMapping->availableColumns()),
            'colleges_catalog_attr_json' => $this->jsonAttribute($this->leadMapping->collegesCatalog()),
            'generate_preview_url' => e(app_url('leads/mapping/generate-preview.php')),
            'preview_page_url' => e(app_url('leads/mapping/mapping-courses-specialization')),
            'duration_defaults_attr_json' => $this->jsonAttribute($durationDefaults),
            'uploaded_batch_id' => e($batchId),
            'mapping_step' => 'region',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ]);
    }

    public function mappingApiColleagues(): void
    {
        redirect('/leads/mapping/mapping-courses-specialization');
    }

    public function generateCourseMappingPreview(): void
    {
        $this->guard();
        $upload = $this->uploadedFileOrRedirect();

        ob_start();
        header('Content-Type: application/json');
        ob_clean();

        try {
            $payload = $this->requestJsonPayload();
            $regions = $this->leadMapping->normalizeRegions((array) ($payload['regions'] ?? []));
            $column = trim((string) ($payload['column'] ?? 'Course'));
            $courses = $this->leadMapping->normalizeCourseValues((array) ($payload['course_values'] ?? []));
            $specialization = trim((string) ($payload['specialization'] ?? ''));
            $colleges = $this->leadMapping->normalizeCollegeIds((array) ($payload['college_ids'] ?? []));
            $rows = $this->leadDataForBatch((string) ($upload['batch_id'] ?? ''))['rows'];

            if ($colleges === []) {
                throw new RuntimeException('Select at least one college before generating preview.');
            }

            if ($specialization === '') {
                throw new RuntimeException('Select one specialization before generating preview.');
            }

            $leads = $this->leadMapping->filterPreviewRows($rows, $regions, $courses, $specialization);
            if ($leads === []) {
                throw new RuntimeException('No leads matched the selected region, course, and specialization filters.');
            }

            $mappingConfigurationId = $this->leadMapping->createMappingConfiguration(
                (string) ($upload['batch_id'] ?? ''),
                $regions,
                $column,
                $courses,
                $specialization,
                $colleges,
                count($leads)
            );

            $_SESSION['mapping_preview'] = [
                'mapping_configuration_id' => $mappingConfigurationId,
                'batch_id' => (string) ($upload['batch_id'] ?? ''),
                'regions' => $regions,
                'column' => $column,
                'course_values' => $courses,
                'specialization' => $specialization,
                'college_ids' => $colleges,
                'colleges' => $this->selectedCollegeNames($colleges),
                'data' => $leads,
                'total' => count($leads),
                'grouped' => $this->leadMapping->groupRowsByRegion($leads),
                'confirmed' => false,
            ];
            $_SESSION['mapped_leads'] = $leads;
            $_SESSION['selected_colleges'] = $this->selectedCollegeNames($colleges);

            echo json_encode([
                'status' => 'success',
                'data' => $leads,
                'total' => count($leads),
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to generate preview.',
            ]);
        }

        exit;
    }

    public function mappingCoursesSpecialization(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $preview = $this->mappingPreviewOrRedirect();
        $durationDefaults = $this->durationDefaults();

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-courses-specialization', [
            'title' => e('Mapping Courses Specialization'),
            'page_kicker' => e('Step 3 of 3'),
            'page_title' => e('Mapping Courses Specialization'),
            'page_description' => e('Review the generated preview, confirm the mapping, set API duration settings, and start background delivery.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_batch_id' => e((string) ($preview['batch_id'] ?? '')),
            'preview_rows_json' => $this->json($preview['data'] ?? []),
            'preview_grouped_attr_json' => $this->jsonAttribute($preview['grouped'] ?? []),
            'preview_total_records' => e((string) ($preview['total'] ?? 0)),
            'preview_selected_regions' => e(implode(', ', (array) ($preview['regions'] ?? []))),
            'preview_selected_courses' => e(implode(', ', (array) ($preview['course_values'] ?? []))),
            'preview_selected_specialization' => e((string) ($preview['specialization'] ?? '')),
            'preview_selected_colleges' => e(implode(', ', (array) ($preview['colleges'] ?? []))),
            'confirm_mapping_url' => e(app_url('leads/mapping/confirm-mapping.php')),
            'save_duration_url' => e(app_url('leads/mapping/save-duration-settings.php')),
            'duration_defaults_attr_json' => $this->jsonAttribute($durationDefaults),
            'mapping_configuration_id' => e((string) ($preview['mapping_configuration_id'] ?? 0)),
            'mapping_confirmed_state' => !empty($preview['confirmed']) ? 'true' : 'false',
            'mapping_step' => 'assign',
        ]);
    }

    public function confirmCourseMapping(): void
    {
        $this->guard();

        ob_start();
        header('Content-Type: application/json');
        ob_clean();

        try {
            $preview = $this->mappingPreviewOrRedirect(false);
            $mappingConfigurationId = (int) ($preview['mapping_configuration_id'] ?? 0);
            if ($mappingConfigurationId <= 0) {
                throw new RuntimeException('Mapping preview session is missing.');
            }

            $this->leadMapping->markConfigurationConfirmed($mappingConfigurationId);
            $_SESSION['mapping_preview']['confirmed'] = true;

            echo json_encode([
                'status' => 'success',
                'data' => [],
                'total' => (int) ($preview['total'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to confirm mapping.',
            ]);
        }

        exit;
    }

    public function saveDurationSettings(): void
    {
        $this->guard();

        ob_start();
        header('Content-Type: application/json');
        ob_clean();

        try {
            $payload = $this->requestJsonPayload();
            $preview = $this->mappingPreviewOrRedirect(false);
            $mappingConfigurationId = (int) ($preview['mapping_configuration_id'] ?? 0);
            $batchSize = $this->normalizeBatchSize($payload['batch_size'] ?? 50, $payload['custom_batch_size'] ?? null);
            $delay = $this->normalizeDelay($payload['delay'] ?? '0.2', $payload['custom_delay'] ?? null);

            if (empty($preview['confirmed'])) {
                throw new RuntimeException('Confirm mapping before saving duration settings.');
            }

            $_SESSION['batch_size'] = $batchSize;
            $_SESSION['delay'] = $delay;

            $job = $this->leadMapping->createOrReuseJob(
                $mappingConfigurationId,
                (string) ($preview['batch_id'] ?? ''),
                $batchSize,
                $delay,
                (int) ($preview['total'] ?? 0),
                (array) ($preview['college_ids'] ?? [])
            );

            $this->leadMapping->spawnBackgroundJob((string) ($job['job_token'] ?? ''));

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'message' => 'Sending leads in background. Redirecting to leads page.',
                    'redirect' => app_url('leads'),
                    'job_token' => (string) ($job['job_token'] ?? ''),
                ],
                'total' => (int) ($preview['total'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to save duration settings.',
            ]);
        }

        exit;
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

    private function mappingPreviewOrRedirect(bool $redirectOnMissing = true): array
    {
        $preview = $_SESSION['mapping_preview'] ?? null;

        if (is_array($preview) && isset($preview['data']) && is_array($preview['data'])) {
            return $preview;
        }

        if ($redirectOnMissing) {
            flash('Generate a preview first to continue the mapping workflow.');
            redirect('/leads/mapping/region');
        }

        throw new RuntimeException('Generate preview first.');
    }

    private function requestJsonPayload(): array
    {
        $rawBody = file_get_contents('php://input');
        $decoded = json_decode(is_string($rawBody) ? $rawBody : '', true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON payload.');
        }

        return $decoded;
    }

    private function selectedCollegeNames(array $collegeIds): array
    {
        $names = [];

        foreach ($this->leadMapping->collegesCatalog() as $college) {
            if (in_array((string) ($college['id'] ?? ''), $collegeIds, true)) {
                $names[] = (string) ($college['name'] ?? $college['id'] ?? '');
            }
        }

        return array_values(array_unique($names));
    }

    private function durationDefaults(): array
    {
        return [
            'batch_size' => 50,
            'delay' => 0.2,
            'batch_options' => [50, 100, 200, 'custom'],
            'delay_options' => ['0.2', '0.35', '1', 'custom'],
        ];
    }

    private function normalizeBatchSize(mixed $batchSize, mixed $customBatchSize): int
    {
        $value = is_string($batchSize) ? trim($batchSize) : (string) $batchSize;
        if ($value === 'custom') {
            $value = is_string($customBatchSize) ? trim($customBatchSize) : (string) $customBatchSize;
        }

        $normalized = (int) $value;
        if ($normalized <= 0) {
            throw new RuntimeException('Batch size must be greater than zero.');
        }

        return $normalized;
    }

    private function normalizeDelay(mixed $delay, mixed $customDelay): float
    {
        $value = is_string($delay) ? trim($delay) : (string) $delay;
        if ($value === 'custom') {
            $value = is_string($customDelay) ? trim($customDelay) : (string) $customDelay;
        }

        $normalized = (float) $value;
        if ($normalized < 0) {
            throw new RuntimeException('Delay must be zero or greater.');
        }

        return $normalized;
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

    private function leadPushLogDataForView(): array
    {
        try {
            $rows = $this->leadPushLogs->all();
        } catch (PDOException) {
            return [
                'rows' => [],
                'headers' => ['Lead ID', 'Region', 'College Name', 'Status', 'Response', 'Created At'],
            ];
        }

        return [
            'rows' => array_map(static function (array $row): array {
                $response = trim((string) ($row['response'] ?? ''));
                if (strlen($response) > 140) {
                    $response = substr($response, 0, 140) . '...';
                }

                return [
                    'Lead ID' => (string) ($row['lead_id'] ?? ''),
                    'Region' => (string) ($row['region'] ?? ''),
                    'College Name' => (string) ($row['college_name'] ?? ''),
                    'Status' => (string) ($row['status'] ?? ''),
                    'Response' => $response,
                    'Created At' => (string) ($row['created_at'] ?? ''),
                ];
            }, $rows),
            'headers' => ['Lead ID', 'Region', 'College Name', 'Status', 'Response', 'Created At'],
        ];
    }

    private function leadPushLogStats(): array
    {
        try {
            return [
                'total_attempts' => $this->leadPushLogs->countAll(),
                'success_count' => $this->leadPushLogs->countByStatus('success'),
                'failed_count' => $this->leadPushLogs->countByStatus('failed'),
                'distinct_colleges' => $this->leadPushLogs->countDistinctColleges(),
            ];
        } catch (PDOException) {
            return [
                'total_attempts' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'distinct_colleges' => 0,
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
            'lead_push_logs_url' => e(app_url('lead-push-logs')),
            'dashboard_active' => $activePage === 'dashboard' ? 'is-active' : '',
            'leads_active' => $activePage === 'leads' ? 'is-active' : '',
            'lead_push_logs_active' => $activePage === 'lead-push-logs' ? 'is-active' : '',
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
            'log_rows_json' => '[]',
            'log_headers_json' => '[]',
            'log_total_records' => '0',
            'log_total_attempts' => '0',
            'log_success_count' => '0',
            'log_failed_count' => '0',
            'log_distinct_colleges' => '0',
            'region_rows_attr_json' => '[]',
            'region_summary_attr_json' => '[]',
            'colleague_catalog_attr_json' => '[]',
            'colleges_catalog_attr_json' => '[]',
            'available_columns_attr_json' => '[]',
            'duration_defaults_attr_json' => '[]',
            'generate_preview_url' => e(app_url('leads/mapping/generate-preview.php')),
            'preview_page_url' => e(app_url('leads/mapping/mapping-courses-specialization')),
            'preview_rows_json' => '[]',
            'preview_grouped_attr_json' => '[]',
            'preview_total_records' => '0',
            'preview_selected_regions' => '',
            'preview_selected_courses' => '',
            'preview_selected_specialization' => '',
            'preview_selected_colleges' => '',
            'confirm_mapping_url' => e(app_url('leads/mapping/confirm-mapping.php')),
            'save_duration_url' => e(app_url('leads/mapping/save-duration-settings.php')),
            'mapping_configuration_id' => '0',
            'mapping_confirmed_state' => 'false',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
            'fetch_colleagues_url' => e(app_url('api/fetch-colleagues.php')),
            'assign_colleagues_url' => e(app_url('api/push-leads.php')),
        ];

        return array_merge($base, $overrides);
    }
}
