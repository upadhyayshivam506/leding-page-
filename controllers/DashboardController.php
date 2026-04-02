<?php

declare(strict_types=1);

namespace Controllers;

use Models\Lead;
use Models\LeadPushLog;
use Models\UploadedLeadFile;
use PDOException;
use RuntimeException;
use Services\AuthService;
use Services\LeadMappingService;
use Services\LeadSchemaService;

final class DashboardController
{
    private const TABLE_PAGE_SIZE = 20;

    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly Lead $leads = new Lead(),
        private readonly LeadPushLog $leadPushLogs = new LeadPushLog(),
        private readonly LeadMappingService $leadMapping = new LeadMappingService(),
        private readonly UploadedLeadFile $uploadedLeadFiles = new UploadedLeadFile(),
        private readonly LeadSchemaService $leadSchema = new LeadSchemaService()
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
            'leads_sent' => e((string) $stats['leads_sent']),
            'failed_leads' => e((string) $stats['failed_leads']),
            'processing_success_rate' => e($stats['processing_success_rate']),
            'upload_activity_bars_html' => $stats['upload_activity_bars_html'],
            'processing_status_rows_html' => $stats['processing_status_rows_html'],
            'recent_uploaded_files_rows_html' => $stats['recent_uploaded_files_rows_html'],
            'dashboard_upload_history_api_url' => e(app_url('api/dashboard/upload-history')),
        ]);
    }

    public function leads(): void
    {
        $user = $this->guard();
        $filters = $this->leadFiltersFromRequest();
        $leadData = $this->leadDataForView($filters, current_page_number(), self::TABLE_PAGE_SIZE);
        $filterOptions = $this->leadFilterOptions();
        $uploadNotice = trim((string) ($_GET['upload_notice'] ?? ''));
        $flashAlert = render_alert(flash(), 'dashboard-alert');
        if ($uploadNotice !== '') {
            $flashAlert .= render_alert([
                'message' => $uploadNotice,
                'type' => 'success',
            ], 'dashboard-alert');
        }

        $this->renderAdminPage($user, 'leads', 'leads/pages/leads', [
            'title' => e('Leads'),
            'page_kicker' => e('Lead workspace'),
            'page_title' => e('Leads'),
            'page_description' => e('Search, filter, export, and review uploaded leads without leaving the page.'),
            'flash_alert' => $flashAlert,
            'page_action' => '',
            'upload_button_html' => $this->uploadButtonHtml(),
            'export_button_html' => $this->exportButtonHtml(),
            'leads_api_url' => e(app_url('api/leads')),
            'leads_export_url' => e(app_url('api/leads/export')),
            'lead_push_status_url' => e(app_url('api/lead-push-job-status')),
            'lead_filters_json' => $this->jsonAttribute($filters),
            'lead_search_value' => e((string) ($filters['search'] ?? '')),
            'lead_date_from_value' => e($this->displayDateValue((string) ($filters['date_from'] ?? ''))),
            'lead_date_to_value' => e($this->displayDateValue((string) ($filters['date_to'] ?? ''))),
            'course_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['course'] ?? [], $filters['course'] ?? []),
            'state_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['state'] ?? [], $filters['state'] ?? []),
            'city_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['city'] ?? [], $filters['city'] ?? []),
            'lead_origin_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['lead_origin'] ?? [], $filters['lead_origin'] ?? []),
            'campaign_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['campaign'] ?? [], $filters['campaign'] ?? []),
            'lead_stage_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['lead_stage'] ?? [], $filters['lead_stage'] ?? []),
            'lead_status_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['lead_status'] ?? [], $filters['lead_status'] ?? []),
            'form_initiated_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['form_initiated'] ?? [], $filters['form_initiated'] ?? []),
            'paid_apps_filter_options_html' => $this->multiSelectOptionsHtml($filterOptions['paid_apps'] ?? [], $filters['paid_apps'] ?? []),
            'main_table_head_html' => $leadData['table_head_html'],
            'main_table_body_html' => $leadData['table_body_html'],
            'main_table_pagination_html' => $leadData['pagination_html'],
            'main_table_count_label' => e($leadData['count_label']),
        ]);
    }

    public function leadsApi(): void
    {
        $this->guard();

        try {
            $filters = $this->leadFiltersFromRequest();
            $this->validateLeadDateRange($filters);
            $page = current_page_number();
            $limit = $this->leadLimitFromRequest();
            $leadData = $this->leadDataForView($filters, $page, $limit);

            $this->jsonResponse([
                'status' => 'success',
                'data' => [
                    'table_head_html' => $leadData['table_head_html'],
                    'table_body_html' => $leadData['table_body_html'],
                    'pagination_html' => $leadData['pagination_html'],
                    'count_label' => $leadData['count_label'],
                ],
            ]);
        } catch (RuntimeException $exception) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Unable to load leads.',
            ], 422);
        }
    }

    public function leadPushJobStatus(): void
    {
        $this->guard();

        $jobToken = trim((string) ($_GET['job_token'] ?? ''));
        if ($jobToken === '') {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Job token is required.',
            ], 422);
        }

        $job = $this->leadMapping->findJobByToken($jobToken);
        if ($job === null) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Lead push job not found.',
            ], 404);
        }

        $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'job_token' => (string) ($job['job_token'] ?? ''),
                'status' => (string) ($job['status'] ?? 'queued'),
                'file_status' => $this->jobDisplayStatus($job),
                'batch_size' => (int) ($job['batch_size'] ?? 0),
                'batch_id' => (string) ($job['batch_id'] ?? ''),
                'total_leads' => (int) ($job['total_leads'] ?? 0),
                'processed_leads' => (int) ($job['processed_leads'] ?? 0),
                'total_requests' => (int) ($job['total_requests'] ?? 0),
                'processed_requests' => (int) ($job['processed_requests'] ?? 0),
                'success_count' => (int) ($job['success_count'] ?? 0),
                'failed_count' => (int) ($job['failed_count'] ?? 0),
            ],
        ]);
    }

    public function dashboardUploadHistory(): void
    {
        $this->guard();

        $stats = $this->leadStats();
        $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'total_uploaded_files' => (int) $stats['total_uploaded_files'],
                'total_leads' => (int) $stats['total_leads'],
                'leads_sent' => (int) $stats['leads_sent'],
                'failed_leads' => (int) $stats['failed_leads'],
                'processing_success_rate' => (string) $stats['processing_success_rate'],
                'upload_activity_bars_html' => (string) $stats['upload_activity_bars_html'],
                'processing_status_rows_html' => (string) $stats['processing_status_rows_html'],
                'recent_uploaded_files_rows_html' => (string) $stats['recent_uploaded_files_rows_html'],
            ],
        ]);
    }
    public function exportLeadsCsv(): void
    {
        $this->guard();

        try {
            $filters = $this->leadFiltersFromRequest();
            $this->validateLeadDateRange($filters);
            $rows = $this->leads->exportByFilters($filters);
            $filename = 'leads_export_' . date('Y-m-d_H-i') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to create the export file.');
            }

            fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, $this->leadSchema->columns());

            foreach ($rows as $row) {
                fputcsv($output, array_values($this->leadSchema->visibleRow($row, (string) ($row['batch_id'] ?? ''))));
            }

            fclose($output);
            exit;
        } catch (RuntimeException $exception) {
            http_response_code(422);
            header('Content-Type: text/plain; charset=UTF-8');
            echo $exception->getMessage() !== '' ? $exception->getMessage() : 'Unable to export leads.';
            exit;
        }
    }

    public function leadPushLogs(): void
    {
        $user = $this->guard();
        $logData = $this->leadPushLogDataForView(current_page_number(), self::TABLE_PAGE_SIZE);
        $logStats = $this->leadPushLogStats();

        $this->renderAdminPage($user, 'lead-push-logs', 'leadslogs/lead-push-logs', [
            'title' => e('Lead Push Logs'),
            'page_kicker' => e('Delivery audit'),
            'page_title' => e('Lead Push Logs'),
            'page_description' => e('Review every API push attempt with status, region, college, response, and delivery timestamp.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
            'main_table_head_html' => $logData['table_head_html'],
            'main_table_body_html' => $logData['table_body_html'],
            'main_table_pagination_html' => $logData['pagination_html'],
            'main_table_count_label' => e($logData['count_label']),
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

        $batchId = uniqid('batch_', true);

        try {
            $leadData = $this->parseUploadedLeadData($destination, $extension, $batchId);
            $inserted = $this->leads->insertMany($this->databaseRows($leadData['rows']), $batchId, $fileName);
            $this->uploadedLeadFiles->create($batchId, $fileName, $storedName, $inserted, $size, 'Uploaded');
        } catch (RuntimeException|PDOException $exception) {
            try {
                $this->uploadedLeadFiles->create($batchId, $fileName, $storedName, 0, $size, 'Failed');
            } catch (\Throwable) {
                // Best effort only. Preserve the original upload error below.
            }
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
        $leadData = $this->leadBatchTableForView(
            (string) ($upload['batch_id'] ?? ''),
            current_page_number(),
            self::TABLE_PAGE_SIZE,
            'leads/mapping',
            'No uploaded lead rows were found for preview.'
        );

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
            'main_table_head_html' => $leadData['table_head_html'],
            'main_table_body_html' => $leadData['table_body_html'],
            'main_table_pagination_html' => $leadData['pagination_html'],
            'main_table_count_label' => e($leadData['count_label']),
            'mapping_step' => 'upload',
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
        $regionTable = $this->leadBatchTableForView(
            $batchId,
            current_page_number(),
            self::TABLE_PAGE_SIZE,
            'leads/mapping/region',
            'No leads are available for region mapping.'
        );
        $summary = $this->leads->summarizeByBatch($batchId);
        $durationDefaults = $this->durationDefaults();

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-region', [
            'title' => e('Region Mapping'),
            'page_kicker' => e('Step 2 of 4'),
            'page_title' => e('Region Grouping'),
            'page_description' => e('Review region grouping, continue directly to assignments, or open the specialization courses mapping page.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_file_time' => e((string) ($upload['uploaded_at'] ?? '')),
            'uploaded_file_type' => e((string) ($upload['extension'] ?? '')),
            'uploaded_file_size' => e(number_format(((int) ($upload['size'] ?? 0)) / 1024, 2) . ' KB'),
            'main_table_head_html' => $regionTable['table_head_html'],
            'main_table_body_html' => $regionTable['table_body_html'],
            'main_table_pagination_html' => $regionTable['pagination_html'],
            'main_table_count_label' => e($regionTable['count_label']),
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
            'mapping_region_courses_url' => e(app_url('leads/mapping/region/courses-mapping')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
        ]);
    }

    public function mappingRegionCoursesMapping(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $batchId = (string) ($upload['batch_id'] ?? '');
        $rows = $this->leadDataForBatch($batchId)['rows'];
        $summary = $this->leads->summarizeByBatch($batchId);

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-region-courses', [
            'title' => e('Specialization Courses Mapping'),
            'page_kicker' => e('Step 3 of 4'),
            'page_title' => e('Specialization Courses Mapping'),
            'page_description' => e('Select detected course values, specialization, and colleges, then confirm the mapping to continue to API Duration.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_batch_id' => e($batchId),
            'region_rows_attr_json' => $this->jsonAttribute($rows),
            'region_summary_attr_json' => $this->jsonAttribute($summary),
            'colleges_catalog_attr_json' => $this->jsonAttribute($this->leadMapping->collegesCatalog()),
            'generate_preview_url' => e(app_url('leads/mapping/generate-preview.php')),
            'preview_page_url' => e(app_url('leads/mapping/mapping-courses-specialization')),
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_step' => 'specialization-mapping',
        ]);
    }

    public function mappingApiColleagues(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $preview = $_SESSION['mapping_preview'] ?? null;
        $rows = is_array($preview) && isset($preview['data']) && is_array($preview['data'])
            ? (array) $preview['data']
            : $this->leadDataForBatch((string) ($upload['batch_id'] ?? ''))['rows'];
        $grouped = is_array($preview) && isset($preview['grouped']) && is_array($preview['grouped'])
            ? (array) $preview['grouped']
            : $this->leadMapping->groupRowsByRegion($rows);
        $assignmentPreviewTable = $this->buildTableView(
            $this->batchLeadHeaders(),
            $rows,
            current_page_number(),
            self::TABLE_PAGE_SIZE,
            'leads/mapping/region/api-colleagues',
            'No leads are available for assignment preview.',
            'leads'
        );
        $requestedSummary = $this->requestedRegionSummary();
        $summary = $requestedSummary !== []
            ? $this->normalizeRegionSummary($requestedSummary, $grouped)
            : $this->summaryFromGroupedRows($grouped);
        $assignments = $this->normalizeAssignments((array) ($_SESSION['region_assignments'] ?? []));
        $selectedCollegeIds = $this->flattenAssignmentCollegeIds($assignments);

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-api-colleagues', [
            'title' => e('Assign Region Colleagues'),
            'page_kicker' => e('Step 3 of 4'),
            'page_title' => e('Assign Region Colleagues'),
            'page_description' => e('Assign colleagues region-wise, confirm the selections, and continue with API duration settings on the same page.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_batch_id' => e((string) ($upload['batch_id'] ?? '')),
            'main_table_head_html' => $assignmentPreviewTable['table_head_html'],
            'main_table_body_html' => $assignmentPreviewTable['table_body_html'],
            'main_table_pagination_html' => $assignmentPreviewTable['pagination_html'],
            'main_table_count_label' => e($assignmentPreviewTable['count_label']),
            'lead_rows_attr_json' => $this->jsonAttribute($rows),
            'region_summary_attr_json' => $this->jsonAttribute($summary),
            'colleague_catalog_attr_json' => $this->jsonAttribute($this->leadMapping->colleagueCatalogByRegion()),
            'confirm_assign_url' => e(app_url('leads/mapping/confirm-assignments.php')),
            'mapping_api_duration_url' => e(app_url('leads/mapping/api-duration')),
            'region_assignments_attr_json' => $this->jsonAttribute($assignments),
            'selected_college_names_attr_json' => $this->jsonAttribute($this->selectedCollegeNames($selectedCollegeIds)),
            'api_duration_total_leads' => e((string) count($rows)),
            'duration_defaults_attr_json' => $this->jsonAttribute($this->durationDefaults()),
            'save_duration_url' => e(app_url('leads/mapping/save-duration-settings.php')),
            'lead_push_status_url' => e(app_url('api/lead-push-job-status')),
            'leads_page_url' => e(app_url('leads')),
            'api_duration_card_visibility_class' => $selectedCollegeIds === [] ? 'd-none' : '',
            'api_duration_selection_value' => e((string) ($_SESSION['api_duration_selection'] ?? '0.35')),
            'mapping_step' => 'assign',
        ]);
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
                throw new RuntimeException('Select one or more colleges before confirming the mapping.');
            }

            if ($specialization === '') {
                throw new RuntimeException('Select one specialization before confirming the mapping.');
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
            $_SESSION['leads_list'] = $leads;
            $_SESSION['selected_colleges'] = $this->selectedCollegeNames($colleges);
            $_SESSION['region_assignments'] = $this->deriveAssignmentsFromPreview($regions, $colleges);

            echo json_encode([
                'status' => 'success',
                'data' => $leads,
                'total' => count($leads),
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to confirm the mapping.',
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
        $assignments = $this->regionAssignmentsFromPreview($preview);
        $selectedCollegeIds = $this->flattenAssignmentCollegeIds($assignments);
        $summary = $this->summaryFromGroupedRows((array) ($preview['grouped'] ?? []));
        $previewTable = $this->buildTableView(
            $this->batchLeadHeaders(),
            (array) ($preview['data'] ?? []),
            current_page_number(),
            self::TABLE_PAGE_SIZE,
            'leads/mapping/mapping-courses-specialization',
            'No leads available in preview.',
            'leads'
        );

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-courses-specialization', [
            'title' => e('API Duration Configuration'),
            'page_kicker' => e('Step 4 of 4'),
            'page_title' => e('API Duration Configuration'),
            'page_description' => e('Review the confirmed specialization mapping, configure the API duration settings, and start sending leads.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region/courses-mapping')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_batch_id' => e((string) ($preview['batch_id'] ?? '')),
            'preview_total_records' => e((string) ($preview['total'] ?? 0)),
            'main_table_head_html' => $previewTable['table_head_html'],
            'main_table_body_html' => $previewTable['table_body_html'],
            'main_table_pagination_html' => $previewTable['pagination_html'],
            'main_table_count_label' => e($previewTable['count_label']),
            'preview_region_groups_html' => $this->renderRegionGroupCards(
                (array) ($preview['grouped'] ?? []),
                10,
                'Preview rows grouped region-wise.',
                'No preview rows in this region.'
            ),
            'preview_selected_regions' => e(implode(', ', (array) ($preview['regions'] ?? []))),
            'preview_selected_courses' => e(implode(', ', (array) ($preview['course_values'] ?? []))),
            'preview_selected_specialization' => e((string) ($preview['specialization'] ?? '')),
            'preview_selected_colleges' => e(implode(', ', (array) ($preview['colleges'] ?? []))),
            'confirm_mapping_url' => e(app_url('leads/mapping/confirm-mapping.php')),
            'save_duration_url' => e(app_url('leads/mapping/save-duration-settings.php')),
            'lead_push_status_url' => e(app_url('api/lead-push-job-status')),
            'leads_page_url' => e(app_url('leads')),
            'duration_defaults_attr_json' => $this->jsonAttribute($durationDefaults),
            'api_duration_total_leads' => e((string) ($preview['total'] ?? 0)),
            'lead_rows_attr_json' => $this->jsonAttribute((array) ($preview['data'] ?? [])),
            'region_summary_attr_json' => $this->jsonAttribute($summary),
            'region_assignments_attr_json' => $this->jsonAttribute($assignments),
            'selected_college_names_attr_json' => $this->jsonAttribute($this->selectedCollegeNames($selectedCollegeIds)),
            'api_duration_selection_value' => e((string) ($_SESSION['api_duration_selection'] ?? '0.35')),
            'mapping_configuration_id' => e((string) ($preview['mapping_configuration_id'] ?? 0)),
            'mapping_confirmed_state' => !empty($preview['confirmed']) ? 'true' : 'false',
            'mapping_region_courses_url' => e(app_url('leads/mapping/region/courses-mapping')),
            'mapping_step' => 'api-duration',
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
                'data' => [
                    'redirect' => app_url('leads/mapping/mapping-courses-specialization'),
                ],
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

    public function confirmRegionAssignments(): void
    {
        $this->guard();

        ob_start();
        header('Content-Type: application/json');
        ob_clean();

        try {
            $payload = $this->requestJsonPayload();
            $upload = $this->uploadedFileOrRedirect();
            $preview = $_SESSION['mapping_preview'] ?? null;
            $rows = is_array($preview) && isset($preview['data']) && is_array($preview['data'])
                ? (array) $preview['data']
                : $this->leadDataForBatch((string) ($upload['batch_id'] ?? ''))['rows'];
            $assignments = $this->normalizeAssignments((array) ($payload['assignments'] ?? []));

            $this->validateAssignmentsForRows($assignments, $rows);

            $_SESSION['selected_colleges'] = $this->flattenAssignmentCollegeIds($assignments);
            $_SESSION['region_assignments'] = $assignments;
            $_SESSION['leads_list'] = $rows;

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'message' => 'Assignments confirmed successfully.',
                    'show_api_duration' => true,
                ],
                'total' => count($rows),
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to confirm colleague assignments.',
            ]);
        }

        exit;
    }

    public function mappingApiDuration(): void
    {
        $user = $this->guard();
        $upload = $this->uploadedFileOrRedirect();
        $rows = isset($_SESSION['leads_list']) && is_array($_SESSION['leads_list'])
            ? (array) $_SESSION['leads_list']
            : $this->leadDataForBatch((string) ($upload['batch_id'] ?? ''))['rows'];
        $assignments = $this->regionAssignmentsOrRedirect();
        $selectedCollegeIds = $this->flattenAssignmentCollegeIds($assignments);

        $this->renderAdminPage($user, 'leads', 'leads/pages/mapping-api-duration', [
            'title' => e('API Duration'),
            'page_kicker' => e('Step 4 of 4'),
            'page_title' => e('API Duration'),
            'page_description' => e('Configure batch size and API duration, then start sending leads automatically in the background.'),
            'flash_alert' => '',
            'page_action' => '<a href="' . e(app_url('leads/mapping/region/api-colleagues')) . '" class="panel-link topbar-action-link">Back</a>',
            'uploaded_file_name' => e((string) ($upload['original_name'] ?? '')),
            'uploaded_batch_id' => e((string) ($upload['batch_id'] ?? '')),
            'api_duration_total_leads' => e((string) count($rows)),
            'lead_rows_attr_json' => $this->jsonAttribute($rows),
            'region_summary_attr_json' => $this->jsonAttribute($this->summaryFromGroupedRows($this->leadMapping->groupRowsByRegion($rows))),
            'duration_defaults_attr_json' => $this->jsonAttribute($this->durationDefaults()),
            'save_duration_url' => e(app_url('leads/mapping/save-duration-settings.php')),
            'lead_push_status_url' => e(app_url('api/lead-push-job-status')),
            'leads_page_url' => e(app_url('leads')),
            'selected_college_names_attr_json' => $this->jsonAttribute($this->selectedCollegeNames($selectedCollegeIds)),
            'region_assignments_attr_json' => $this->jsonAttribute($assignments),
            'mapping_api_duration_url' => e(app_url('leads/mapping/api-duration')),
            'api_duration_selection_value' => e((string) ($_SESSION['api_duration_selection'] ?? '0.35')),
            'mapping_step' => 'api-duration',
        ]);
    }

    public function saveDurationSettings(): void
    {
        $this->guard();

        ob_start();
        header('Content-Type: application/json');
        ob_clean();

        try {
            $payload = $this->requestJsonPayload();
            $upload = $this->uploadedFileOrRedirect();
            $preview = $_SESSION['mapping_preview'] ?? null;
            $rows = isset($_SESSION['leads_list']) && is_array($_SESSION['leads_list'])
                ? (array) $_SESSION['leads_list']
                : (is_array($preview) && isset($preview['data']) && is_array($preview['data'])
                    ? (array) $preview['data']
                    : $this->leadDataForBatch((string) ($upload['batch_id'] ?? ''))['rows']);
            $mappingConfigurationId = is_array($preview) ? (int) ($preview['mapping_configuration_id'] ?? 0) : 0;
            $payloadRows = $this->normalizeLeadRowsPayload((array) ($payload['leads_data'] ?? []));
            if ($payloadRows !== []) {
                $rows = $payloadRows;
            }
            $batchSize = $this->normalizeBatchSize($payload['batch_size'] ?? 50, $payload['custom_batch_size'] ?? null);
            $delay = $this->normalizeDelay($payload['delay'] ?? '1', $payload['custom_delay'] ?? null);
            $apiDurationSelection = $this->normalizeApiDurationSelection($payload['api_duration_selection'] ?? ($payload['api_duration'] ?? null));
            $assignmentsPayload = $payload['assignments'] ?? ($payload['assigned_colleagues'] ?? null);
            $assignments = is_array($assignmentsPayload)
                ? $this->normalizeAssignments($assignmentsPayload)
                : $this->regionAssignmentsOrRedirect(false);

            if ($mappingConfigurationId <= 0) {
                $mappingConfigurationId = $this->leadMapping->createMappingConfiguration(
                    (string) ($upload['batch_id'] ?? ''),
                    $this->leadMapping->regions(),
                    'Region',
                    [],
                    '',
                    [],
                    count($rows)
                );
            }

            $this->validateAssignmentsForRows($assignments, $rows);

            $_SESSION['batch_size'] = $batchSize;
            $_SESSION['delay'] = $delay;
            $_SESSION['api_duration_selection'] = $apiDurationSelection;
            $_SESSION['selected_colleges'] = $this->flattenAssignmentCollegeIds($assignments);
            $_SESSION['region_assignments'] = $assignments;
            $_SESSION['leads_list'] = $rows;

            $job = $this->leadMapping->createOrReuseJob(
                $mappingConfigurationId,
                (string) ($upload['batch_id'] ?? ''),
                $batchSize,
                $delay,
                $rows,
                $assignments
            );

            $this->leadMapping->spawnBackgroundJob((string) ($job['job_token'] ?? ''));

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'confirmation' => 'Duration settings saved successfully.',
                    'message' => 'API requests are being processed in the background.',
                    'redirect' => app_url('leads?lead_push_job_token=' . rawurlencode((string) ($job['job_token'] ?? '')) . '&lead_push_total=' . count($rows)),
                    'job_token' => (string) ($job['job_token'] ?? ''),
                    'api_duration_selection' => $apiDurationSelection,
                ],
                'total' => count($rows),
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

    public function systemConfig(): void
    {
        $user = $this->guard();
        $errors = validation_errors();

        $this->renderAdminPage($user, 'system-config', 'systemconfig/system-config', [
            'title' => e('System Config'),
            'page_kicker' => e('Platform controls'),
            'page_title' => e('System Config'),
            'page_description' => e('Use this page for environment-level settings, database options, upload rules, and system-wide controls.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
            'change_password_action' => e(app_url('system-config/change-password')),
            'account_email' => e((string) ($user['email'] ?? env('ADMIN_EMAIL', 'admin@gmail.com'))),
            'current_password_error' => e((string) ($errors['current_password'] ?? '')),
            'new_password_error' => e((string) ($errors['new_password'] ?? '')),
            'confirm_password_error' => e((string) ($errors['confirm_password'] ?? '')),
        ]);
    }

    public function viewUploadedFile(): void
    {
        $this->guard();

        try {
            $batchId = trim((string) ($_GET['batch_id'] ?? ''));
            $upload = $this->trackedUploadOrFail($batchId);
            unset(
                $_SESSION['mapping_preview'],
                $_SESSION['region_assignments'],
                $_SESSION['leads_list'],
                $_SESSION['selected_colleges'],
                $_SESSION['batch_size'],
                $_SESSION['delay'],
                $_SESSION['api_duration_selection']
            );
            $_SESSION['last_uploaded_lead_file'] = $this->sessionUploadPayload($upload);
            $rows = [];
            try {
                $rows = $this->mapBatchLeadRows($this->leads->findByBatch($batchId));
            } catch (PDOException) {
                $rows = [];
            }
            $_SESSION['uploaded_lead_data'] = [
                'rows' => $rows,
                'headers' => $this->batchLeadHeaders(),
            ];
            redirect('/leads/mapping');
        } catch (RuntimeException $exception) {
            flash($exception->getMessage() !== '' ? $exception->getMessage() : 'Uploaded file not found.');
            redirect('/dashboard');
        }
    }

    public function retryUploadedFilePush(): void
    {
        $this->guard();

        try {
            $batchId = trim((string) ($_GET['batch_id'] ?? ''));
            $upload = $this->trackedUploadOrFail($batchId);
            $job = $this->leadMapping->retryLatestBatchJob($batchId);
            $this->uploadedLeadFiles->updateStatus($batchId, 'Processing', (string) ($job['job_token'] ?? ''));
            flash('Retry push started for ' . (string) ($upload['file_name'] ?? 'the selected file') . '.', 'success');
        } catch (RuntimeException $exception) {
            flash($exception->getMessage() !== '' ? $exception->getMessage() : 'Unable to retry the selected push.');
        }

        redirect('/dashboard');
    }

    public function downloadUploadedFileLog(): void
    {
        $this->guard();

        try {
            $batchId = trim((string) ($_GET['batch_id'] ?? ''));
            $upload = $this->trackedUploadOrFail($batchId);
            $rows = $this->leadPushLogs->findByBatch($batchId);
            $filenameBase = pathinfo((string) ($upload['file_name'] ?? ('batch_' . $batchId)), PATHINFO_FILENAME);
            $filename = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $filenameBase);
            $filename = trim((string) $filename, '_');
            if ($filename === '') {
                $filename = 'upload_log_' . date('Ymd_His');
            }

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '_log.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to create the log download.');
            }

            fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, ['Batch ID', 'Lead ID', 'Name', 'Email', 'Mobile', 'Course', 'Specialization', 'Campus', 'College', 'City', 'State', 'Region', 'Source File', 'Status', 'Attempt', 'Response', 'Created At']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    (string) ($row['batch_id'] ?? ''),
                    (string) ($row['lead_id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['course'] ?? ''),
                    (string) ($row['specialization'] ?? ''),
                    (string) ($row['campus'] ?? ''),
                    (string) ($row['college_name'] ?? ''),
                    (string) ($row['city'] ?? ''),
                    (string) ($row['state'] ?? ''),
                    (string) ($row['region'] ?? ''),
                    (string) ($row['source_file'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['attempt_no'] ?? ''),
                    (string) ($row['response'] ?? ''),
                    (string) ($row['created_at'] ?? ''),
                ]);
            }

            fclose($output);
            exit;
        } catch (RuntimeException $exception) {
            flash($exception->getMessage() !== '' ? $exception->getMessage() : 'Unable to download the upload log.');
            redirect('/dashboard');
        }
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
            . csrf_field()
            . '<input type="hidden" name="parsed_rows_json" value="" data-upload-rows-json>'
            . '<input type="hidden" name="parsed_headers_json" value="" data-upload-headers-json>'
            . '<label class="panel-link topbar-action-link upload-trigger leads-action-button">Upload Excel File<input type="file" name="lead_file" accept=".xls,.xlsx,.csv" class="d-none" data-upload-input></label>'
            . '</form>';
    }

    private function exportButtonHtml(): string
    {
        return '<button type="button" class="panel-link topbar-action-link leads-action-button" data-export-leads>Export</button>';
    }

    private function leadDataForView(array $filters, int $currentPage, int $recordsPerPage): array
    {
        $headers = $this->leadListHeaders();

        try {
            $totalRecords = $this->leads->countByFilters($filters);
            if ($totalRecords > 0) {
                $pagination = pagination_state($totalRecords, $recordsPerPage, $currentPage);
                $rows = $this->mapLeadListRows($this->leads->paginateByFilters($filters, $pagination['records_per_page'], $pagination['offset']));

                return $this->buildTableFromPagination(
                    $headers,
                    $rows,
                    $pagination,
                    'leads',
                    'Upload a lead file to see lead rows here.',
                    'leads'
                );
            }
        } catch (PDOException) {
            return $this->buildTableView(
                $headers,
                [],
                $currentPage,
                $recordsPerPage,
                'leads',
                'No leads matched the current search and filters.',
                'leads'
            );
        }

        return $this->buildTableView(
            $headers,
            [],
            $currentPage,
            $recordsPerPage,
            'leads',
            'No leads matched the current search and filters.',
            'leads'
        );
    }

    private function leadBatchTableForView(
        string $batchId,
        int $currentPage,
        int $recordsPerPage,
        string $basePath,
        string $emptyMessage
    ): array {
        $headers = $this->batchLeadHeaders();
        $rows = [];
        $totalRecords = 0;

        if ($batchId !== '') {
            try {
                $totalRecords = $this->leads->countByBatch($batchId);
                if ($totalRecords > 0) {
                    $pagination = pagination_state($totalRecords, $recordsPerPage, $currentPage);
                    $rows = $this->mapBatchLeadRows($this->leads->findByBatchPaginated($batchId, $pagination['records_per_page'], $pagination['offset']));

                    return $this->buildTableFromPagination(
                        $headers,
                        $rows,
                        $pagination,
                        $basePath,
                        $emptyMessage,
                        'leads'
                    );
                }
            } catch (PDOException) {
                $rows = [];
            }
        }

        $fallbackRows = $this->uploadedLeadData()['rows'];

        return $this->buildTableView(
            $headers,
            $fallbackRows,
            $currentPage,
            $recordsPerPage,
            $basePath,
            $emptyMessage,
            'leads'
        );
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
            'rows' => $this->mapBatchLeadRows($rows),
            'headers' => $this->batchLeadHeaders(),
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

    private function parseUploadedLeadData(string $filePath, string $extension, string $batchId = ''): array
    {
        $rows = $this->parseSpreadsheetRows($filePath, $extension);

        if ($rows === []) {
            $rows = $this->parsedRowsFromRequest();
        }

        $rows = $this->prepareRows($rows, $batchId);
        $headers = $this->batchLeadHeaders();

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

    private function prepareRows(array $rawRows, string $batchId = ''): array
    {
        $prepared = [];

        foreach ($rawRows as $index => $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $row = $this->leadSchema->mapIncomingRow($rawRow, $index, $batchId);

            if (!$this->leadSchema->hasVisibleContent($row)) {
                continue;
            }

            $prepared[] = $row;
        }

        if ($prepared === []) {
            throw new RuntimeException('No lead rows were found in the uploaded file.');
        }

        return $prepared;
    }

    private function databaseRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->leadSchema->databaseRowFromSchema($row), $rows);
    }

    private function leadListHeaders(): array
    {
        return $this->leadSchema->columns();
    }

    private function batchLeadHeaders(): array
    {
        return $this->leadSchema->columns();
    }

    private function logHeaders(): array
    {
        return ['Batch ID', 'Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region', 'Source File', 'Status', 'Attempt', 'Response', 'Created At'];
    }

    private function mapLeadListRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->leadSchema->visibleRow($row, (string) ($row['batch_id'] ?? '')), $rows);
    }

    private function mapBatchLeadRows(array $rows): array
    {
        return array_map(
            fn (array $row): array => $this->leadSchema->mapStoredRow(
                $row,
                (string) ($row['batch_id'] ?? $row['Batch ID'] ?? '')
            ),
            $rows
        );
    }

    private function mapLogRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            $response = trim((string) ($row['response'] ?? ''));
            $response = preg_replace('/\s+/', ' ', $response ?? '');
            $response = is_string($response) ? trim($response) : '';
            if (strlen($response) > 220) {
                $response = substr($response, 0, 220) . '...';
            }

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
                'Status' => (string) ($row['status'] ?? ''),
                'Attempt' => (string) ($row['attempt_no'] ?? '1'),
                'Response' => $response,
                'Created At' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    private function leadFiltersFromRequest(): array
    {
        return [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'course' => $this->csvValues($_GET['course'] ?? ''),
            'state' => $this->csvValues($_GET['state'] ?? ''),
            'city' => $this->csvValues($_GET['city'] ?? ''),
            'lead_origin' => $this->csvValues($_GET['lead_origin'] ?? ''),
            'campaign' => $this->csvValues($_GET['campaign'] ?? ''),
            'lead_stage' => $this->csvValues($_GET['lead_stage'] ?? ''),
            'lead_status' => $this->csvValues($_GET['lead_status'] ?? ''),
            'form_initiated' => $this->csvValues($_GET['form_initiated'] ?? ''),
            'paid_apps' => $this->csvValues($_GET['paid_apps'] ?? ''),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
    }

    private function leadFilterOptions(): array
    {
        $defaults = [
            'course' => [],
            'state' => [],
            'city' => [],
            'lead_origin' => [],
            'campaign' => [],
            'lead_stage' => [],
            'lead_status' => [],
            'form_initiated' => [],
            'paid_apps' => [],
        ];

        try {
            return array_merge($defaults, $this->leads->filterOptions());
        } catch (PDOException) {
            return $defaults;
        }
    }

    private function csvValues(mixed $value): array
    {
        if (is_array($value)) {
            $values = $value;
        } else {
            $raw = trim((string) $value);
            $values = $raw === '' ? [] : explode(',', $raw);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $values
        ), static fn (string $item): bool => $item !== '')));
    }

    private function multiSelectOptionsHtml(array $options, array $selectedValues): string
    {
        $selectedLookup = array_fill_keys($selectedValues, true);
        $html = [];

        foreach ($options as $option) {
            $value = trim((string) $option);
            if ($value === '') {
                continue;
            }

            $selected = isset($selectedLookup[$value]) ? ' selected' : '';
            $html[] = '<option value="' . e($value) . '"' . $selected . '>' . e($value) . '</option>';
        }

        return implode('', $html);
    }

    private function displayDateValue(string $value): string
    {
        $normalized = $this->normalizeDateString($value);
        if ($normalized === null) {
            return trim($value);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $normalized);

        return $date instanceof \DateTimeImmutable ? $date->format('d-m-Y') : trim($value);
    }

    private function normalizeDateString(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function leadLimitFromRequest(): int
    {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

        return min(200, max(1, (int) ($limit ?: self::TABLE_PAGE_SIZE)));
    }

    private function validateLeadDateRange(array $filters): void
    {
        $dateFromRaw = trim((string) ($filters['date_from'] ?? ''));
        $dateToRaw = trim((string) ($filters['date_to'] ?? ''));
        $dateFrom = $this->normalizeDateString($dateFromRaw);
        $dateTo = $this->normalizeDateString($dateToRaw);

        if ($dateFromRaw !== '' && $dateFrom === null) {
            throw new RuntimeException('Use dd-mm-yyyy for the From date.');
        }

        if ($dateToRaw !== '' && $dateTo === null) {
            throw new RuntimeException('Use dd-mm-yyyy for the To date.');
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new RuntimeException('The From date cannot be later than the To date.');
        }
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function buildTableView(
        array $headers,
        array $rows,
        int $currentPage,
        int $recordsPerPage,
        string $basePath,
        string $emptyMessage,
        string $itemLabel = 'rows'
    ): array {
        $pagination = paginate_array($rows, $currentPage, $recordsPerPage);

        return $this->buildTableFromPagination(
            $headers,
            $pagination['rows'],
            $pagination,
            $basePath,
            $emptyMessage,
            $itemLabel
        );
    }

    private function buildTableFromPagination(
        array $headers,
        array $rows,
        array $pagination,
        string $basePath,
        string $emptyMessage,
        string $itemLabel = 'rows'
    ): array {
        $countLabel = $pagination['total_records'] > 0
            ? 'Showing ' . $pagination['from_record'] . '-' . $pagination['to_record'] . ' of ' . $pagination['total_records'] . ' ' . $itemLabel
            : '0 ' . $itemLabel;

        return [
            'table_head_html' => render_table_head($headers),
            'table_body_html' => render_table_body($headers, $rows, $emptyMessage),
            'pagination_html' => generatePagination((int) $pagination['current_page'], (int) $pagination['total_pages'], $basePath),
            'count_label' => $countLabel,
        ];
    }

    private function renderRegionGroupCards(array $groupedRows, int $previewLimit, string $description, string $emptyMessage): string
    {
        $cards = [];

        foreach ($this->leadMapping->regions() as $region) {
            $rows = array_values((array) ($groupedRows[$region] ?? []));
            $cards[] = '<article class="region-group-card">'
                . '<div class="panel-head panel-head--table"><div><h3>' . e($region) . '</h3><p class="table-subtext">' . e($description) . '</p></div><span class="panel-chip">' . e((string) count($rows)) . ' leads</span></div>'
                . $this->renderCompactTableHtml(array_slice($this->mapBatchLeadRows($rows), 0, $previewLimit), $this->batchLeadHeaders(), $emptyMessage)
                . '</article>';
        }

        return implode('', $cards);
    }

    private function renderCompactTableHtml(array $rows, array $headers, string $emptyMessage): string
    {
        return '<div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead>'
            . render_table_head($headers)
            . '</thead><tbody>'
            . render_table_body($headers, $rows, $emptyMessage)
            . '</tbody></table></div>';
    }

    private function renderUploadActivityBars(array $weeklyUploads): string
    {
        $uploads = $weeklyUploads !== [] ? $weeklyUploads : array_map(
            static fn (string $label): array => ['label' => $label, 'total' => 0],
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        );
        $max = max(1, ...array_map(static fn (array $row): int => (int) ($row['total'] ?? 0), $uploads));

        return implode('', array_map(static function (array $row) use ($max): string {
            $total = (int) ($row['total'] ?? 0);
            $height = max(12, (int) round(($total / $max) * 100));

            return '<div class="activity-bar" title="' . e((string) $total) . ' uploads"><span style="height: ' . e((string) $height) . '%"></span><small>' . e((string) ($row['label'] ?? '')) . '</small></div>';
        }, $uploads));
    }

    private function renderProcessingStatusRows(array $statusCounts): string
    {
        $rows = [
            ['label' => 'Completed', 'count' => (int) ($statusCounts['Completed'] ?? 0), 'dot' => 'success'],
            ['label' => 'Processing', 'count' => (int) ($statusCounts['Processing'] ?? 0), 'dot' => 'warning'],
            ['label' => 'Partial', 'count' => (int) ($statusCounts['Partial'] ?? 0), 'dot' => 'purple'],
            ['label' => 'Failed', 'count' => (int) ($statusCounts['Failed'] ?? 0), 'dot' => 'danger'],
            ['label' => 'Uploaded', 'count' => (int) ($statusCounts['Uploaded'] ?? 0), 'dot' => 'muted'],
        ];

        return implode('', array_map(
            static fn (array $row): string => '<div><i class="legend-dot legend-dot--' . e((string) $row['dot']) . '"></i> '
                . e((string) $row['label']) . ' <strong>' . e(number_format((int) $row['count'])) . '</strong></div>',
            $rows
        ));
    }

    private function renderRecentUploadedFilesRows(array $files): string
    {
        if ($files === []) {
            return '<tr><td colspan="5" class="table-empty-state">No uploaded files are available yet.</td></tr>';
        }

        return implode('', array_map(function (array $file): string {
            $batchId = (string) ($file['batch_id'] ?? '');
            $status = (string) ($file['status'] ?? 'Uploaded');
            $viewUrl = app_url('dashboard/uploads/view?batch_id=' . rawurlencode($batchId));
            $retryUrl = app_url('dashboard/uploads/retry?batch_id=' . rawurlencode($batchId));
            $downloadUrl = app_url('dashboard/uploads/download-log?batch_id=' . rawurlencode($batchId));

            return '<tr>'
                . '<td>' . e((string) ($file['file_name'] ?? '')) . '</td>'
                . '<td>' . e($this->formatDashboardDate((string) ($file['upload_date'] ?? ''))) . '</td>'
                . '<td>' . e(number_format((int) ($file['total_leads'] ?? 0))) . '</td>'
                . '<td><span class="status-pill ' . e($this->statusPillClass($status)) . '">' . e($status) . '</span></td>'
                . '<td>'
                . '<a href="' . e($viewUrl) . '" class="table-action">View</a> '
                . '<a href="' . e($retryUrl) . '" class="table-action">Retry Push</a> '
                . '<a href="' . e($downloadUrl) . '" class="table-action">Download Log</a>'
                . '</td>'
                . '</tr>';
        }, $files));
    }

    private function statusPillClass(string $status): string
    {
        return match (strtolower(trim($status))) {
            'completed' => 'status-pill--success',
            'failed' => 'status-pill--danger',
            'partial' => 'status-pill--warning',
            'processing' => 'status-pill--warning',
            default => '',
        };
    }

    private function formatDashboardDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('M d, Y H:i', $timestamp) : $value;
    }

    private function jobDisplayStatus(array $job): string
    {
        $status = strtolower(trim((string) ($job['status'] ?? 'uploaded')));
        $totalRequests = max(0, (int) ($job['total_requests'] ?? 0));
        $processedRequests = max(0, (int) ($job['processed_requests'] ?? 0));
        $successCount = max(0, (int) ($job['success_count'] ?? 0));
        $failedCount = max(0, (int) ($job['failed_count'] ?? 0));

        if (in_array($status, ['queued', 'processing'], true)) {
            return 'Processing';
        }

        if ($totalRequests > 0 && $processedRequests >= $totalRequests) {
            if ($failedCount === 0 && $successCount > 0) {
                return 'Completed';
            }

            if ($successCount === 0 && $failedCount > 0) {
                return 'Failed';
            }

            if ($successCount > 0 && $failedCount > 0) {
                return 'Partial';
            }
        }

        return 'Uploaded';
    }

    private function trackedUploadOrFail(string $batchId): array
    {
        if ($batchId === '') {
            throw new RuntimeException('Batch ID is required.');
        }

        $upload = $this->uploadedLeadFiles->findByBatch($batchId);
        if ($upload === null) {
            throw new RuntimeException('Uploaded file not found.');
        }

        return $upload;
    }

    private function sessionUploadPayload(array $upload): array
    {
        return [
            'original_name' => (string) ($upload['file_name'] ?? ''),
            'stored_name' => (string) ($upload['stored_name'] ?? ''),
            'uploaded_at' => (string) ($upload['upload_date'] ?? ''),
            'size' => (int) ($upload['file_size'] ?? 0),
            'extension' => strtoupper((string) pathinfo((string) ($upload['file_name'] ?? ''), PATHINFO_EXTENSION)),
            'batch_id' => (string) ($upload['batch_id'] ?? ''),
        ];
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

    private function normalizeRegionSummary(array $requestedSummary, array $groupedRows): array
    {
        $indexed = [];

        foreach ($requestedSummary as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $region = trim((string) ($entry['region'] ?? ''));
            if ($region === '') {
                continue;
            }

            $indexed[$region] = max(0, (int) ($entry['total'] ?? 0));
        }

        $summary = [];

        foreach ($this->leadMapping->regions() as $region) {
            $summary[] = [
                'region' => $region,
                'total' => $indexed[$region] ?? count((array) ($groupedRows[$region] ?? [])),
            ];
        }

        return $summary;
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

    private function flattenAssignmentCollegeIds(array $assignments): array
    {
        $flattened = [];

        foreach ($assignments as $regionAssignments) {
            foreach ((array) $regionAssignments as $collegeId) {
                $collegeId = trim((string) $collegeId);
                if ($collegeId !== '' && !in_array($collegeId, $flattened, true)) {
                    $flattened[] = $collegeId;
                }
            }
        }

        return $flattened;
    }

    private function normalizeAssignments(array $assignments): array
    {
        $normalized = [];

        foreach ($this->leadMapping->regions() as $region) {
            $normalized[$region] = $this->leadMapping->normalizeCollegeIds((array) ($assignments[$region] ?? []));
        }

        return $normalized;
    }

    private function validateAssignmentsForRows(array $assignments, array $rows): void
    {
        if ($this->flattenAssignmentCollegeIds($assignments) === []) {
            $availableByRegion = $this->leadMapping->colleagueCatalogByRegion();
            $hasAnyAssignableRegion = false;

            foreach ($this->leadMapping->groupRowsByRegion($rows) as $region => $groupedRows) {
                if (count((array) $groupedRows) > 0 && count((array) ($availableByRegion[$region] ?? [])) > 0) {
                    $hasAnyAssignableRegion = true;
                    break;
                }
            }

            if ($hasAnyAssignableRegion) {
                throw new RuntimeException('Please select at least one colleague.');
            }
        }

        $groupedRows = $this->leadMapping->groupRowsByRegion($rows);
        $availableByRegion = $this->leadMapping->colleagueCatalogByRegion();
        foreach ($this->leadMapping->regions() as $region) {
            $hasLeads = count((array) ($groupedRows[$region] ?? [])) > 0;
            $hasAvailableColleagues = count((array) ($availableByRegion[$region] ?? [])) > 0;

            if ($hasLeads && $hasAvailableColleagues && (array) ($assignments[$region] ?? []) === []) {
                throw new RuntimeException('Select at least one colleague for the ' . $region . ' region.');
            }
        }
    }

    private function regionAssignmentsOrRedirect(bool $redirectOnMissing = true): array
    {
        $assignments = $_SESSION['region_assignments'] ?? null;
        if (!is_array($assignments)) {
            if ($redirectOnMissing) {
                flash('Confirm region colleague assignments first.');
                redirect('/leads/mapping/region/api-colleagues');
            }

            throw new RuntimeException('Confirm region colleague assignments first.');
        }

        return $this->normalizeAssignments($assignments);
    }

    private function summaryFromGroupedRows(array $groupedRows): array
    {
        $summary = [];

        foreach ($this->leadMapping->regions() as $region) {
            $summary[] = [
                'region' => $region,
                'total' => count((array) ($groupedRows[$region] ?? [])),
            ];
        }

        return $summary;
    }

    private function durationDefaults(): array
    {
        return [
            'batch_size' => 50,
            'delay' => 0.35,
            'api_duration' => '0.35',
            'batch_options' => [50, 100, 200],
            'delay_options' => ['0.35', '0.50', '1 sec'],
            'api_duration_options' => ['0.35', '0.50', '1 sec'],
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

    private function normalizeApiDurationSelection(mixed $selection): string
    {
        $value = trim((string) $selection);
        if ($value === '') {
            return '0.35';
        }

        $allowed = ['0.35', '0.50', '1 sec'];
        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException('Select a valid API duration option.');
        }

        return $value;
    }

    private function regionAssignmentsFromPreview(array $preview): array
    {
        $sessionAssignments = $_SESSION['region_assignments'] ?? null;
        if (is_array($sessionAssignments)) {
            return $this->normalizeAssignments($sessionAssignments);
        }

        return $this->deriveAssignmentsFromPreview(
            (array) ($preview['regions'] ?? []),
            (array) ($preview['college_ids'] ?? [])
        );
    }

    private function deriveAssignmentsFromPreview(array $regions, array $collegeIds): array
    {
        $selectedRegions = $this->leadMapping->normalizeRegions($regions);
        $selectedCollegeIds = $this->leadMapping->normalizeCollegeIds($collegeIds);
        $assignments = array_fill_keys($this->leadMapping->regions(), []);

        if ($selectedCollegeIds === []) {
            return $this->normalizeAssignments($assignments);
        }

        $catalogByRegion = $this->leadMapping->colleagueCatalogByRegion();

        foreach ($selectedRegions as $region) {
            $matchingCollegeIds = array_values(array_map(
                static fn (array $college): string => (string) ($college['id'] ?? ''),
                array_values(array_filter(
                    (array) ($catalogByRegion[$region] ?? []),
                    static fn (array $college): bool => in_array((string) ($college['id'] ?? ''), $selectedCollegeIds, true)
                ))
            ));

            $assignments[$region] = $matchingCollegeIds !== [] ? $matchingCollegeIds : $selectedCollegeIds;
        }

        return $this->normalizeAssignments($assignments);
    }

    private function normalizeLeadRowsPayload(array $rows): array
    {
        $filtered = array_values(array_filter($rows, static fn ($row): bool => is_array($row)));
        if ($filtered === []) {
            return [];
        }

        return $this->leadSchema->normalizePayloadRows($filtered);
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
            $statusCounts = $this->uploadedLeadFiles->countsByStatus();
            $weeklyUploads = $this->uploadedLeadFiles->uploadCountsByDay(7);
            $completedFiles = (int) ($statusCounts['Completed'] ?? 0);
            $partialFiles = (int) ($statusCounts['Partial'] ?? 0);
            $failedFiles = (int) ($statusCounts['Failed'] ?? 0);
            $processedFiles = max(0, $completedFiles + $partialFiles + $failedFiles);
            $successRate = $processedFiles > 0
                ? (string) round(($completedFiles / $processedFiles) * 100) . '%'
                : '0%';

            return [
                'total_leads' => $this->leads->countAll(),
                'total_uploaded_files' => $this->uploadedLeadFiles->countAll(),
                'leads_sent' => $this->leadPushLogs->countByStatus('success'),
                'failed_leads' => $this->leadPushLogs->countByStatus('failed'),
                'processing_success_rate' => $successRate,
                'upload_activity_bars_html' => $this->renderUploadActivityBars($weeklyUploads),
                'processing_status_rows_html' => $this->renderProcessingStatusRows($statusCounts),
                'recent_uploaded_files_rows_html' => $this->renderRecentUploadedFilesRows($this->uploadedLeadFiles->latest(5)),
            ];
        } catch (PDOException) {
            return [
                'total_leads' => 0,
                'total_uploaded_files' => 0,
                'leads_sent' => 0,
                'failed_leads' => 0,
                'processing_success_rate' => '0%',
                'upload_activity_bars_html' => $this->renderUploadActivityBars([]),
                'processing_status_rows_html' => $this->renderProcessingStatusRows([]),
                'recent_uploaded_files_rows_html' => $this->renderRecentUploadedFilesRows([]),
            ];
        }
    }

    private function leadPushLogDataForView(int $currentPage, int $recordsPerPage): array
    {
        $headers = $this->logHeaders();

        try {
            $totalRecords = $this->leadPushLogs->countAll();
            $pagination = pagination_state($totalRecords, $recordsPerPage, $currentPage);
            $rows = $this->mapLogRows($this->leadPushLogs->paginate($pagination['records_per_page'], $pagination['offset']));

            return $this->buildTableFromPagination(
                $headers,
                $rows,
                $pagination,
                'lead-push-logs',
                'No lead push logs are available yet.',
                'logs'
            );
        } catch (PDOException) {
            return $this->buildTableView(
                $headers,
                [],
                $currentPage,
                $recordsPerPage,
                'lead-push-logs',
                'No lead push logs are available yet.',
                'logs'
            );
        }
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
            'csrf_token' => e(csrf_token()),
            'csrf_field' => csrf_field(),
            'css_url' => e(asset('css/app.css')),
            'js_url' => e(asset('js/app.js')),
            'user_email' => e((string) ($user['email'] ?? '')),
            'logged_in_at' => e((string) ($user['logged_in_at'] ?? '-')),
            'dashboard_url' => e(app_url('dashboard')),
            'leads_url' => e(app_url('leads')),
            'system_config_url' => e(app_url('system-config')),
            'lead_push_logs_url' => e(app_url('lead-push-logs')),
            'dashboard_active' => $activePage === 'dashboard' ? 'is-active' : '',
            'leads_active' => $activePage === 'leads' ? 'is-active' : '',
            'lead_push_logs_active' => $activePage === 'lead-push-logs' ? 'is-active' : '',
            'system_config_active' => $activePage === 'system-config' ? 'is-active' : '',
            'flash_alert' => '',
            'page_action' => '',
            'upload_button_html' => '',
            'export_button_html' => '',
            'leads_api_url' => e(app_url('api/leads')),
            'leads_export_url' => e(app_url('api/leads/export')),
            'lead_filters_json' => '[]',
            'lead_search_value' => '',
            'lead_date_from_value' => '',
            'lead_date_to_value' => '',
            'course_filter_options_html' => '',
            'state_filter_options_html' => '',
            'city_filter_options_html' => '',
            'lead_origin_filter_options_html' => '',
            'campaign_filter_options_html' => '',
            'lead_stage_filter_options_html' => '',
            'lead_status_filter_options_html' => '',
            'form_initiated_filter_options_html' => '',
            'paid_apps_filter_options_html' => '',
            'uploaded_file_name' => '',
            'uploaded_file_time' => '',
            'uploaded_file_type' => '',
            'uploaded_file_size' => '',
            'uploaded_batch_id' => '',
            'total_students' => '0',
            'total_uploaded_files' => '0',
            'leads_sent' => '0',
            'failed_leads' => '0',
            'processing_success_rate' => '0%',
            'upload_activity_bars_html' => '',
            'processing_status_rows_html' => '',
            'recent_uploaded_files_rows_html' => '',
            'dashboard_upload_history_api_url' => '',
            'mapping_step' => '',
            'main_table_head_html' => '',
            'main_table_body_html' => '',
            'main_table_pagination_html' => '',
            'main_table_count_label' => '0 rows',
            'preview_region_groups_html' => '',
            'api_duration_total_leads' => '0',
            'log_total_attempts' => '0',
            'log_success_count' => '0',
            'log_failed_count' => '0',
            'log_distinct_colleges' => '0',
            'region_rows_attr_json' => '[]',
            'lead_rows_attr_json' => '[]',
            'region_summary_attr_json' => '[]',
            'colleague_catalog_attr_json' => '[]',
            'colleges_catalog_attr_json' => '[]',
            'available_columns_attr_json' => '[]',
            'duration_defaults_attr_json' => '[]',
            'generate_preview_url' => e(app_url('leads/mapping/generate-preview.php')),
            'preview_page_url' => e(app_url('leads/mapping/mapping-courses-specialization')),
            'mapping_region_courses_url' => e(app_url('leads/mapping/region/courses-mapping')),
            'preview_total_records' => '0',
            'preview_selected_regions' => '',
            'preview_selected_courses' => '',
            'preview_selected_specialization' => '',
            'preview_selected_colleges' => '',
            'confirm_mapping_url' => e(app_url('leads/mapping/confirm-mapping.php')),
            'confirm_assign_url' => e(app_url('leads/mapping/confirm-assignments.php')),
            'save_duration_url' => e(app_url('leads/mapping/save-duration-settings.php')),
            'mapping_configuration_id' => '0',
            'mapping_confirmed_state' => 'false',
            'mapping_region_url' => e(app_url('leads/mapping/region')),
            'mapping_api_url' => e(app_url('leads/mapping/region/api-colleagues')),
            'mapping_api_duration_url' => e(app_url('leads/mapping/api-duration')),
            'region_assignments_attr_json' => '[]',
            'selected_college_names_attr_json' => '[]',
            'api_duration_card_visibility_class' => 'd-none',
            'api_duration_selection_value' => '0.35',
            'fetch_colleagues_url' => e(app_url('api/fetch-colleagues.php')),
            'assign_colleagues_url' => e(app_url('api/push-leads.php')),
            'change_password_action' => e(app_url('system-config/change-password')),
            'account_email' => '',
            'current_password_error' => '',
            'new_password_error' => '',
            'confirm_password_error' => '',
        ];

        return array_merge($base, $overrides);
    }
}
