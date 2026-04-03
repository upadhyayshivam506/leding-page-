<?php

declare(strict_types=1);

namespace Services;

use Config\Database;
use Models\UploadedLeadFile;
use PDO;
use RuntimeException;

final class LeadMappingService
{
    private const REGION_ORDER = ['North', 'South', 'East', 'West / Others'];
    private const MAX_RETRIES = 2;

    public function ensureTables(): void
    {
        $connection = Database::connection();
        (new UploadedLeadFile());

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS colleagues (
                id VARCHAR(100) NOT NULL,
                college_name VARCHAR(190) NOT NULL,
                region VARCHAR(50) NOT NULL,
                api_url VARCHAR(255) DEFAULT NULL,
                api_token VARCHAR(255) DEFAULT NULL,
                recommended_source VARCHAR(120) DEFAULT NULL,
                external_college_id VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY colleagues_region_index (region),
                KEY colleagues_college_name_index (college_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureColleaguesColumns($connection);

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS lead_mapping_configurations (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                batch_id VARCHAR(100) NOT NULL,
                selected_regions JSON NOT NULL,
                mapping_column VARCHAR(100) NOT NULL,
                selected_courses JSON NOT NULL,
                selected_specialization VARCHAR(190) DEFAULT NULL,
                selected_colleges JSON NOT NULL,
                course_conversion_json JSON DEFAULT NULL,
                specialization_conversion_json JSON DEFAULT NULL,
                total_leads INT NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT "previewed",
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY lead_mapping_configurations_batch_id_index (batch_id),
                KEY lead_mapping_configurations_status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS lead_mapping_jobs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                mapping_configuration_id BIGINT(20) UNSIGNED NOT NULL,
                batch_id VARCHAR(100) NOT NULL,
                job_token VARCHAR(120) NOT NULL,
                batch_size INT NOT NULL,
                delay_seconds DECIMAL(8,2) NOT NULL DEFAULT 0.20,
                start_time TIME DEFAULT NULL,
                end_time TIME DEFAULT NULL,
                status VARCHAR(50) NOT NULL DEFAULT "queued",
                total_leads INT NOT NULL DEFAULT 0,
                total_requests INT NOT NULL DEFAULT 0,
                processed_leads INT NOT NULL DEFAULT 0,
                processed_requests INT NOT NULL DEFAULT 0,
                success_count INT NOT NULL DEFAULT 0,
                failed_count INT NOT NULL DEFAULT 0,
                colleges_json JSON NOT NULL,
                leads_json LONGTEXT DEFAULT NULL,
                started_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY lead_mapping_jobs_job_token_unique (job_token),
                UNIQUE KEY lead_mapping_jobs_mapping_configuration_unique (mapping_configuration_id),
                KEY lead_mapping_jobs_status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureLeadMappingConfigurationColumns($connection);
        $this->ensureLeadMappingJobsColumns($connection);

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS lead_api_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                mapping_configuration_id BIGINT(20) UNSIGNED DEFAULT NULL,
                job_token VARCHAR(120) DEFAULT NULL,
                batch_id VARCHAR(100) DEFAULT NULL,
                lead_id VARCHAR(100) NOT NULL,
                name VARCHAR(190) DEFAULT NULL,
                email VARCHAR(190) DEFAULT NULL,
                mobile VARCHAR(50) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                course VARCHAR(190) DEFAULT NULL,
                specialization VARCHAR(190) DEFAULT NULL,
                campus VARCHAR(190) DEFAULT NULL,
                college_name VARCHAR(190) NOT NULL,
                api_url VARCHAR(255) DEFAULT NULL,
                city VARCHAR(120) DEFAULT NULL,
                state VARCHAR(120) DEFAULT NULL,
                region VARCHAR(50) DEFAULT NULL,
                source_file VARCHAR(255) DEFAULT NULL,
                status VARCHAR(50) NOT NULL,
                response LONGTEXT DEFAULT NULL,
                request_key VARCHAR(160) DEFAULT NULL,
                attempt_no INT NOT NULL DEFAULT 1,
                schema_json JSON DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY lead_api_logs_batch_id_index (batch_id),
                KEY lead_api_logs_lead_id_index (lead_id),
                KEY lead_api_logs_college_name_index (college_name),
                KEY lead_api_logs_status_index (status),
                KEY lead_api_logs_request_key_index (request_key),
                KEY lead_api_logs_job_token_index (job_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureLeadApiLogsColumns($connection);

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS lead_push_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                mapping_configuration_id BIGINT(20) UNSIGNED DEFAULT NULL,
                job_token VARCHAR(120) DEFAULT NULL,
                batch_id VARCHAR(100) DEFAULT NULL,
                lead_id VARCHAR(100) NOT NULL,
                college_id VARCHAR(100) NOT NULL,
                college_name VARCHAR(190) DEFAULT NULL,
                api_url VARCHAR(255) DEFAULT NULL,
                region VARCHAR(50) DEFAULT NULL,
                course VARCHAR(190) DEFAULT NULL,
                specialization VARCHAR(190) DEFAULT NULL,
                total_records INT NOT NULL DEFAULT 1,
                status VARCHAR(50) DEFAULT NULL,
                api_status VARCHAR(50) NOT NULL DEFAULT "queued",
                response_message LONGTEXT DEFAULT NULL,
                response LONGTEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY lead_push_logs_batch_id_index (batch_id),
                KEY lead_push_logs_lead_id_index (lead_id),
                KEY lead_push_logs_region_index (region),
                KEY lead_push_logs_college_id_index (college_id),
                KEY lead_push_logs_status_index (status),
                KEY lead_push_logs_job_token_index (job_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS leads_main_table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                mapping_configuration_id BIGINT(20) UNSIGNED DEFAULT NULL,
                job_token VARCHAR(120) DEFAULT NULL,
                batch_id VARCHAR(100) DEFAULT NULL,
                source_lead_id VARCHAR(100) NOT NULL,
                college_id VARCHAR(100) DEFAULT NULL,
                college_name VARCHAR(190) DEFAULT NULL,
                name VARCHAR(190) DEFAULT NULL,
                email VARCHAR(190) DEFAULT NULL,
                mobile VARCHAR(50) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                course VARCHAR(190) DEFAULT NULL,
                specialization VARCHAR(190) DEFAULT NULL,
                state VARCHAR(120) DEFAULT NULL,
                city VARCHAR(120) DEFAULT NULL,
                region VARCHAR(50) DEFAULT NULL,
                push_status VARCHAR(50) NOT NULL DEFAULT "pending",
                response_message LONGTEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY leads_main_table_batch_id_index (batch_id),
                KEY leads_main_table_source_lead_id_index (source_lead_id),
                KEY leads_main_table_college_id_index (college_id),
                KEY leads_main_table_push_status_index (push_status),
                KEY leads_main_table_job_token_index (job_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureLeadPushLogsColumns($connection);
        $this->ensureLeadsMainTableColumns($connection);
        $this->seedDefaultColleagues($connection);
    }

    public function availableColumns(): array
    {
        return ['Course', 'Specialization', 'Campus', 'College', 'City', 'State', 'Region'];
    }

    public function regions(): array
    {
        return self::REGION_ORDER;
    }

    public function colleagueCatalogByRegion(): array
    {
        $this->ensureTables();

        $catalog = array_fill_keys(self::REGION_ORDER, []);
        $statement = Database::connection()->query(
            'SELECT id, college_name, region, api_url, api_token, recommended_source, external_college_id
             FROM colleagues
             ORDER BY region ASC, college_name ASC'
        );
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $region = trim((string) ($row['region'] ?? ''));
            if ($region === '') {
                $region = 'West / Others';
            }
            if (!isset($catalog[$region])) {
                $catalog[$region] = [];
            }

            if ($region === 'East') {
                continue;
            }

            $catalog[$region][] = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['college_name'] ?? $row['id'] ?? ''),
                'region' => $region,
                'api_endpoint' => (string) ($row['api_url'] ?? ''),
                'api_token' => (string) ($row['api_token'] ?? ''),
                'recommended_source' => (string) ($row['recommended_source'] ?? ''),
                'external_college_id' => (string) ($row['external_college_id'] ?? ''),
            ];
        }

        return $catalog;
    }

    public function colleagueSelectionCatalogByRegion(): array
    {
        $safeCatalog = array_fill_keys(self::REGION_ORDER, []);

        foreach ($this->colleagueCatalogByRegion() as $region => $colleges) {
            $safeCatalog[$region] = array_map(static function (array $college) use ($region): array {
                return [
                    'id' => (string) ($college['id'] ?? ''),
                    'name' => (string) ($college['name'] ?? $college['id'] ?? ''),
                    'region' => (string) $region,
                ];
            }, (array) $colleges);
        }

        return $safeCatalog;
    }

    public function collegesCatalog(): array
    {
        $catalog = [];

        foreach ($this->colleagueCatalogByRegion() as $region => $colleges) {
            foreach ((array) $colleges as $college) {
                $catalog[] = [
                    'id' => (string) ($college['id'] ?? ''),
                    'name' => (string) ($college['name'] ?? $college['id'] ?? ''),
                    'region' => (string) $region,
                ];
            }
        }

        usort($catalog, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $catalog;
    }

    public function collegeById(string $collegeId): ?array
    {
        $collegeId = trim($collegeId);
        if ($collegeId === '') {
            return null;
        }

        return $this->findCollegeById($collegeId);
    }

    public function normalizeRegions(array $regions): array
    {
        $selected = array_values(array_unique(array_filter(array_map(
            static fn ($region): string => trim((string) $region),
            $regions
        ), fn (string $region): bool => in_array($region, self::REGION_ORDER, true))));

        return $selected === [] ? self::REGION_ORDER : $selected;
    }

    public function normalizeCourseValues(array $courses): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $courses
        ), static fn (string $value): bool => $value !== '')));
    }

    public function normalizeCollegeIds(array $collegeIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $collegeIds
        ), static fn (string $value): bool => $value !== '')));
    }

    public function uniqueCourseValues(array $rows, array $regions): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !in_array((string) ($row['Region'] ?? ''), $regions, true)) {
                continue;
            }

            $value = trim((string) ($row['Course'] ?? ''));
            if ($value !== '') {
                $values[$value] = $value;
            }
        }

        ksort($values);

        return array_values($values);
    }

    public function specializationValues(array $rows, array $regions, array $courseValues): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (!$this->rowMatchesRegionAndCourse($row, $regions, $courseValues)) {
                continue;
            }

            $value = trim((string) ($row['Specialization'] ?? ''));
            if ($value !== '') {
                $values[$value] = $value;
            }
        }

        ksort($values);

        return array_values($values);
    }

    public function filterPreviewRows(array $rows, array $regions, array $courseValues, string $specialization): array
    {
        $filtered = [];
        $schema = new LeadSchemaService();

        foreach ($rows as $row) {
            if (!$this->rowMatchesRegionAndCourse($row, $regions, $courseValues)) {
                continue;
            }

            if ($specialization !== '' && strcasecmp(trim((string) ($row['Specialization'] ?? '')), $specialization) !== 0) {
                continue;
            }

            $filtered[] = $schema->mapStoredRow($row, (string) ($row['Batch ID'] ?? $row['batch_id'] ?? ''));
        }

        return $filtered;
    }

    public function groupRowsByRegion(array $rows): array
    {
        $grouped = array_fill_keys(self::REGION_ORDER, []);

        foreach ($rows as $row) {
            $region = (string) ($row['Region'] ?? 'West / Others');
            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }

            $grouped[$region][] = $row;
        }

        return $grouped;
    }

    public function createMappingConfiguration(
        string $batchId,
        array $regions,
        string $mappingColumn,
        array $courses,
        string $specialization,
        array $collegeIds,
        int $totalLeads,
        array $courseConversions = [],
        array $specializationConversions = []
    ): int {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'INSERT INTO lead_mapping_configurations (
                batch_id, selected_regions, mapping_column, selected_courses, selected_specialization, selected_colleges, course_conversion_json, specialization_conversion_json, total_leads, status
             ) VALUES (
                :batch_id, :selected_regions, :mapping_column, :selected_courses, :selected_specialization, :selected_colleges, :course_conversion_json, :specialization_conversion_json, :total_leads, :status
             )'
        );

        $statement->execute([
            'batch_id' => $batchId,
            'selected_regions' => $this->encodeJson($regions),
            'mapping_column' => trim($mappingColumn) !== '' ? $mappingColumn : 'Course',
            'selected_courses' => $this->encodeJson($courses),
            'selected_specialization' => $specialization !== '' ? $specialization : null,
            'selected_colleges' => $this->encodeJson($collegeIds),
            'course_conversion_json' => $courseConversions !== [] ? $this->encodeJsonValue($courseConversions) : null,
            'specialization_conversion_json' => $specializationConversions !== [] ? $this->encodeJsonValue($specializationConversions) : null,
            'total_leads' => $totalLeads,
            'status' => 'previewed',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function markConfigurationConfirmed(int $mappingConfigurationId): void
    {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'UPDATE lead_mapping_configurations
             SET status = :status
             WHERE id = :id'
        );
        $statement->execute([
            'status' => 'confirmed',
            'id' => $mappingConfigurationId,
        ]);
    }

    public function createOrReuseJob(
        int $mappingConfigurationId,
        string $batchId,
        int $batchSize,
        float $delay,
        array $leads,
        array $assignmentsByRegion,
        array $options = []
    ): array
    {
        $this->ensureTables();

        $existing = $this->findJobByConfiguration($mappingConfigurationId);
        if ($existing !== null && in_array((string) ($existing['status'] ?? ''), ['queued', 'processing', 'completed'], true)) {
            $this->syncUploadFileStatusByJobToken((string) ($existing['job_token'] ?? ''));
            return $existing;
        }

        $jobToken = 'map_job_' . bin2hex(random_bytes(10));
        $totalRequests = $this->calculateTotalRequests($leads, $assignmentsByRegion);
        $statement = Database::connection()->prepare(
            'INSERT INTO lead_mapping_jobs (
                mapping_configuration_id, batch_id, job_token, batch_size, delay_seconds, start_time, end_time, status, total_leads, total_requests, colleges_json, leads_json
             ) VALUES (
                :mapping_configuration_id, :batch_id, :job_token, :batch_size, :delay_seconds, :start_time, :end_time, :status, :total_leads, :total_requests, :colleges_json, :leads_json
             )'
        );
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId,
            'batch_id' => $batchId,
            'job_token' => $jobToken,
            'batch_size' => $batchSize,
            'delay_seconds' => number_format($delay, 2, '.', ''),
            'start_time' => $this->normalizeTimeOrNull($options['start_time'] ?? null),
            'end_time' => $this->normalizeTimeOrNull($options['end_time'] ?? null),
            'status' => 'queued',
            'total_leads' => count($leads),
            'total_requests' => $totalRequests,
            'colleges_json' => $this->encodeJsonValue($assignmentsByRegion),
            'leads_json' => $this->encodeJsonValue($leads),
        ]);

        $job = $this->findJobByToken($jobToken);
        if ($job === null) {
            throw new RuntimeException('Unable to create background sending job.');
        }

        (new UploadedLeadFile())->updateStatus($batchId, 'Uploaded', (string) ($job['job_token'] ?? ''));

        return $job;
    }

    public function findJobByConfiguration(int $mappingConfigurationId): ?array
    {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'SELECT * FROM lead_mapping_jobs
             WHERE mapping_configuration_id = :mapping_configuration_id
             LIMIT 1'
        );
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId,
        ]);

        $job = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($job) ? $job : null;
    }

    public function findJobByToken(string $jobToken): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM lead_mapping_jobs
             WHERE job_token = :job_token
             LIMIT 1'
        );
        $statement->execute([
            'job_token' => $jobToken,
        ]);

        $job = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($job) ? $job : null;
    }

    public function retryLatestBatchJob(string $batchId): array
    {
        $batchId = trim($batchId);
        if ($batchId === '') {
            throw new RuntimeException('Batch ID is required to retry the lead push.');
        }

        $latestJob = $this->latestJobByBatch($batchId);
        if ($latestJob === null) {
            throw new RuntimeException('No previous API push job was found for this file.');
        }

        if ((string) ($latestJob['status'] ?? '') === 'queued') {
            $this->spawnBackgroundJob((string) ($latestJob['job_token'] ?? ''));
            $this->syncUploadFileStatusByJobToken((string) ($latestJob['job_token'] ?? ''));

            return $latestJob;
        }

        if ((string) ($latestJob['status'] ?? '') === 'processing') {
            $this->syncUploadFileStatusByJobToken((string) ($latestJob['job_token'] ?? ''));

            return $latestJob;
        }

        $mappingConfiguration = $this->findMappingConfiguration((int) ($latestJob['mapping_configuration_id'] ?? 0));
        if ($mappingConfiguration === null) {
            throw new RuntimeException('Unable to load the last mapping configuration for retry.');
        }

        $specialization = trim((string) ($mappingConfiguration['selected_specialization'] ?? ''));
        $assignmentsByRegion = $this->decodeJsonMap($latestJob['colleges_json'] ?? '{}');
        $leads = $this->decodedLeadsFromJob($latestJob);
        if ($leads === []) {
            $regions = $this->decodeJsonArray($mappingConfiguration['selected_regions'] ?? '[]');
            $courses = $this->decodeJsonArray($mappingConfiguration['selected_courses'] ?? '[]');
            $rows = $this->fetchBatchRows($batchId);
            $leads = $this->filterPreviewRows($rows, $regions, $courses, $specialization);
        }

        if ($leads === []) {
            throw new RuntimeException('No mapped leads are available for retry.');
        }

        $mappingConfigurationId = $this->duplicateMappingConfiguration($mappingConfiguration, count($leads));
        $job = $this->createOrReuseJob(
            $mappingConfigurationId,
            $batchId,
            max(1, (int) ($latestJob['batch_size'] ?? 50)),
            max(0.0, (float) ($latestJob['delay_seconds'] ?? 0.35)),
            $leads,
            $assignmentsByRegion,
            [
                'start_time' => $latestJob['start_time'] ?? null,
                'end_time' => $latestJob['end_time'] ?? null,
            ]
        );

        $this->spawnBackgroundJob((string) ($job['job_token'] ?? ''));

        return $job;
    }

    public function spawnBackgroundJob(string $jobToken): void
    {
        $jobToken = trim($jobToken);
        if ($jobToken === '') {
            throw new RuntimeException('Background job token is missing.');
        }

        $runner = base_path('background/run-mapping-job.php');
        if (!is_file($runner)) {
            throw new RuntimeException('Background runner file is missing.');
        }

        $phpBinary = $this->resolvePhpBinary();
        $phpBinary = escapeshellarg($phpBinary);
        $runnerArg = escapeshellarg($runner);
        $tokenArg = escapeshellarg($jobToken);
        $logFile = $this->jobOutputLogPath($jobToken);
        $logArg = escapeshellarg($logFile);

        if (DIRECTORY_SEPARATOR === '\\') {
            $command = 'cmd /c start "" /B ' . $phpBinary . ' ' . $runnerArg . ' ' . $tokenArg . ' >> ' . $logArg . ' 2>&1';

            if (function_exists('popen')) {
                @pclose(@popen($command, 'r'));
                return;
            }

            @exec($command);
            return;
        }

        @exec($phpBinary . ' ' . $runnerArg . ' ' . $tokenArg . ' >> ' . $logArg . ' 2>&1 &');
    }

    private function resolvePhpBinary(): string
    {
        $candidates = [];

        $configured = getenv('APP_PHP_BINARY');
        if (is_string($configured) && trim($configured) !== '') {
            $candidates[] = trim($configured);
        }

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }

        $xamppRoot = dirname(dirname(base_path()));
        $candidates = array_merge($candidates, [
            $xamppRoot . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe',
            'C:\\xampp\\php\\php.exe',
            'C:\\php\\php.exe',
            '/Applications/XAMPP/xamppfiles/bin/php',
            '/Applications/XAMPP/bin/php',
            'php',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === 'php') {
                return $candidate;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    private function jobOutputLogPath(string $jobToken): string
    {
        $directory = base_path('uploads/logs');
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'lead-mapping-job-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $jobToken) . '.log';
    }

    public function runQueuedJob(string $jobToken): void
    {
        $job = $this->findJobByToken($jobToken);
        if ($job === null) {
            throw new RuntimeException('Background job not found.');
        }

        $mappingConfiguration = $this->findMappingConfiguration((int) ($job['mapping_configuration_id'] ?? 0));
        if ($mappingConfiguration === null) {
            throw new RuntimeException('Mapping configuration not found for background job.');
        }

        $assignmentsByRegion = $this->decodeJsonMap($job['colleges_json'] ?? '{}');
        $leads = $this->decodedLeadsFromJob($job);
        if ($leads === []) {
            $regions = $this->decodeJsonArray($mappingConfiguration['selected_regions'] ?? '[]');
            $courses = $this->decodeJsonArray($mappingConfiguration['selected_courses'] ?? '[]');
            $specialization = trim((string) ($mappingConfiguration['selected_specialization'] ?? ''));
            $rows = $this->fetchBatchRows((string) ($mappingConfiguration['batch_id'] ?? ''));
            $leads = $this->filterPreviewRows($rows, $regions, $courses, $specialization);
        }

        $this->processJob($jobToken, $leads, $assignmentsByRegion, (int) ($mappingConfiguration['id'] ?? 0), (string) ($mappingConfiguration['batch_id'] ?? ''));
    }

    public function processJob(string $jobToken, array $leads, array $assignmentsByRegion, int $mappingConfigurationId, string $batchId): void
    {
        $job = $this->findJobByToken($jobToken);
        if ($job === null) {
            throw new RuntimeException('Background job not found.');
        }

        if (in_array((string) ($job['status'] ?? ''), ['processing', 'completed'], true)) {
            return;
        }

        $batchSize = max(1, (int) ($job['batch_size'] ?? 50));
        $delay = max(0.0, (float) ($job['delay_seconds'] ?? 0.2));
        $batches = array_chunk($leads, $batchSize);

        $this->updateJobState($jobToken, 'processing', 'started_at = NOW()');
        $this->syncUploadFileStatus($batchId, $jobToken);

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $lead) {
                $leadRegion = (string) ($lead['Region'] ?? 'West / Others');
                $collegeIds = $this->normalizeCollegeIds((array) ($assignmentsByRegion[$leadRegion] ?? []));

                foreach ($collegeIds as $collegeId) {
                    $requestKey = sha1($mappingConfigurationId . '|' . (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? '') . '|' . $collegeId);
                    if ($this->hasSuccessfulLog($requestKey)) {
                        continue;
                    }

                    $college = $this->findCollegeById($collegeId);
                    if ($college === null) {
                        $this->storeLeadLog($mappingConfigurationId, $jobToken, $batchId, $lead, $collegeId, null, 'failed', 'College configuration not found.', $requestKey, 1);
                        $mainLeadId = $this->storeLeadMainRecord($mappingConfigurationId, $jobToken, $batchId, $lead, [
                            'id' => $collegeId,
                            'name' => $collegeId,
                        ]);
                        $this->updateLeadMainRecordStatus($mainLeadId, 'failed', 'College configuration not found.');
                        $this->storeLeadPushDeliveryLog($mappingConfigurationId, $jobToken, $batchId, $lead, [
                            'id' => $collegeId,
                            'name' => $collegeId,
                        ], 'failed', 'College configuration not found.');
                        $this->incrementJobCounters($jobToken, false);
                        continue;
                    }

                    $mainLeadId = $this->storeLeadMainRecord($mappingConfigurationId, $jobToken, $batchId, $lead, $college);
                    $result = $this->retryLeadSend($lead, $college, $requestKey, $mappingConfigurationId, $jobToken, $batchId);
                    $this->updateLeadMainRecordStatus($mainLeadId, $result['success'] ? 'sent' : 'failed', (string) ($result['response'] ?? ''));
                    $this->storeLeadPushDeliveryLog(
                        $mappingConfigurationId,
                        $jobToken,
                        $batchId,
                        $lead,
                        $college,
                        $result['success'] ? 'sent' : 'failed',
                        (string) ($result['response'] ?? '')
                    );
                    $this->incrementJobCounters($jobToken, $result['success']);
                }

                $this->incrementProcessedLeads($jobToken);
            }

            if ($batchIndex < count($batches) - 1 && $delay > 0) {
                usleep((int) round($delay * 1000000));
            }
        }

        $this->updateJobState($jobToken, 'completed', 'completed_at = NOW()');
        $this->updateConfigurationStatus($mappingConfigurationId, 'completed');
        $this->syncUploadFileStatus($batchId, $jobToken);
    }

    public function storeLeadLog(
        int $mappingConfigurationId,
        string $jobToken,
        string $batchId,
        array $lead,
        string $collegeName,
        ?string $apiUrl,
        string $status,
        string $response,
        string $requestKey,
        int $attemptNo
    ): void {
        $this->ensureTables();
        $payload = $this->collegeLeadPayload($lead);

        $statement = Database::connection()->prepare(
            'INSERT INTO lead_api_logs (
                mapping_configuration_id, job_token, batch_id, lead_id, name, email, mobile, phone, course, specialization, campus, college_name, api_url, city, state, region, source_file, status, response, request_key, attempt_no, schema_json, created_at
             ) VALUES (
                :mapping_configuration_id, :job_token, :batch_id, :lead_id, :name, :email, :mobile, :phone, :course, :specialization, :campus, :college_name, :api_url, :city, :state, :region, :source_file, :status, :response, :request_key, :attempt_no, :schema_json, NOW()
             )'
        );
        $mobile = $this->stringOrNull($lead['Mobile'] ?? $lead['Phone'] ?? null);
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId > 0 ? $mappingConfigurationId : null,
            'job_token' => $jobToken !== '' ? $jobToken : null,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'lead_id' => (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? ''),
            'name' => $this->stringOrNull($payload['name'] ?? null),
            'email' => $this->stringOrNull($payload['email'] ?? null),
            'mobile' => $this->stringOrNull($payload['mobile'] ?? null),
            'phone' => $mobile,
            'course' => $this->stringOrNull($payload['course'] ?? null),
            'specialization' => null,
            'campus' => null,
            'college_name' => $collegeName,
            'api_url' => $this->stringOrNull($apiUrl),
            'city' => $this->stringOrNull($payload['city'] ?? null),
            'state' => $this->stringOrNull($payload['state'] ?? null),
            'region' => $this->stringOrNull($lead['Region'] ?? null),
            'source_file' => $this->stringOrNull($lead['Source File'] ?? null),
            'status' => $status,
            'response' => $response,
            'request_key' => $requestKey !== '' ? $requestKey : null,
            'attempt_no' => max(1, $attemptNo),
            'schema_json' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function storeLeadPushDeliveryLog(
        int $mappingConfigurationId,
        string $jobToken,
        string $batchId,
        array $lead,
        array $college,
        string $status,
        string $response
    ): void {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'INSERT INTO lead_push_logs (
                mapping_configuration_id, job_token, batch_id, lead_id, college_id, college_name, api_url, region, course, specialization, total_records, status, api_status, response_message, response, created_at
             ) VALUES (
                :mapping_configuration_id, :job_token, :batch_id, :lead_id, :college_id, :college_name, :api_url, :region, :course, :specialization, :total_records, :status, :api_status, :response_message, :response, NOW()
             )'
        );
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId > 0 ? $mappingConfigurationId : null,
            'job_token' => $jobToken !== '' ? $jobToken : null,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'lead_id' => (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? ''),
            'college_id' => (string) ($college['id'] ?? ''),
            'college_name' => $this->stringOrNull($college['name'] ?? $college['id'] ?? null),
            'api_url' => $this->stringOrNull($college['api_endpoint'] ?? null),
            'region' => $this->stringOrNull($lead['Region'] ?? null),
            'course' => $this->stringOrNull($lead['Course'] ?? null),
            'specialization' => $this->stringOrNull($lead['Specialization'] ?? null),
            'total_records' => 1,
            'status' => $status,
            'api_status' => $status,
            'response_message' => $response,
            'response' => $response,
        ]);
    }

    public function storeLeadMainRecord(
        int $mappingConfigurationId,
        string $jobToken,
        string $batchId,
        array $lead,
        array $college
    ): int {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'INSERT INTO leads_main_table (
                mapping_configuration_id, job_token, batch_id, source_lead_id, college_id, college_name, name, email, mobile, phone, course, specialization, state, city, region, push_status, response_message, created_at
             ) VALUES (
                :mapping_configuration_id, :job_token, :batch_id, :source_lead_id, :college_id, :college_name, :name, :email, :mobile, :phone, :course, :specialization, :state, :city, :region, :push_status, :response_message, NOW()
             )'
        );
        $mobile = $this->stringOrNull($lead['Mobile'] ?? $lead['Phone'] ?? null);
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId > 0 ? $mappingConfigurationId : null,
            'job_token' => $jobToken !== '' ? $jobToken : null,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'source_lead_id' => (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? ''),
            'college_id' => $this->stringOrNull($college['id'] ?? null),
            'college_name' => $this->stringOrNull($college['name'] ?? $college['id'] ?? null),
            'name' => $this->stringOrNull($lead['Name'] ?? null),
            'email' => $this->stringOrNull($lead['Email'] ?? null),
            'mobile' => $mobile,
            'phone' => $mobile,
            'course' => $this->stringOrNull($lead['Course'] ?? null),
            'specialization' => $this->stringOrNull($lead['Specialization'] ?? null),
            'state' => $this->stringOrNull($lead['State'] ?? null),
            'city' => $this->stringOrNull($lead['City'] ?? null),
            'region' => $this->stringOrNull($lead['Region'] ?? null),
            'push_status' => 'pending',
            'response_message' => null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateLeadMainRecordStatus(int $leadMainId, string $status, string $response): void
    {
        if ($leadMainId <= 0) {
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE leads_main_table
             SET push_status = :push_status,
                 response_message = :response_message
             WHERE id = :id'
        );
        $statement->execute([
            'push_status' => $status,
            'response_message' => $response,
            'id' => $leadMainId,
        ]);
    }

    public function storeSelectedLeadPushSummaryLog(
        array $leads,
        array $college,
        string $status,
        string $response,
        int $totalRecords
    ): void {
        $this->ensureTables();

        $firstLead = is_array($leads[0] ?? null) ? $leads[0] : [];
        $batchIds = array_values(array_unique(array_filter(array_map(
            static fn ($lead): string => trim((string) (($lead['Batch ID'] ?? ''))),
            $leads
        ), static fn (string $batchId): bool => $batchId !== '')));
        $batchId = count($batchIds) === 1 ? $batchIds[0] : null;

        $statement = Database::connection()->prepare(
            'INSERT INTO lead_push_logs (
                mapping_configuration_id, job_token, batch_id, lead_id, college_id, college_name, api_url, region, course, specialization, total_records, status, api_status, response_message, response, created_at
             ) VALUES (
                NULL, NULL, :batch_id, :lead_id, :college_id, :college_name, :api_url, :region, :course, :specialization, :total_records, :status, :api_status, :response_message, :response, NOW()
             )'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'lead_id' => trim((string) ($firstLead['__lead_id'] ?? $firstLead['Lead ID'] ?? 'selected-leads')) ?: 'selected-leads',
            'college_id' => (string) ($college['id'] ?? ''),
            'college_name' => $this->stringOrNull($college['name'] ?? $college['id'] ?? null),
            'api_url' => $this->stringOrNull($college['api_endpoint'] ?? null),
            'region' => $this->stringOrNull($firstLead['Region'] ?? null),
            'course' => $this->stringOrNull($firstLead['Course'] ?? null),
            'specialization' => $this->stringOrNull($firstLead['Specialization'] ?? null),
            'total_records' => max(1, $totalRecords),
            'status' => $status,
            'api_status' => $status,
            'response_message' => $response,
            'response' => $response,
        ]);
    }

    public function sendSelectedLeadsToCollege(array $leadRows, string $collegeId): array
    {
        $this->ensureTables();

        $college = $this->findCollegeById($collegeId);
        if ($college === null) {
            throw new RuntimeException('Selected college configuration was not found.');
        }

        $schema = new LeadSchemaService();
        $leads = [];
        foreach ($leadRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $leads[] = $schema->mapStoredRow($row, (string) ($row['batch_id'] ?? ''));
        }

        if ($leads === []) {
            throw new RuntimeException('No selected leads were found.');
        }

        $successCount = 0;
        $failedCount = 0;
        $responses = [];

        foreach ($leads as $index => $lead) {
            $batchId = trim((string) ($lead['Batch ID'] ?? ''));
            $requestKey = 'selected_' . sha1($collegeId . '|' . (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? '') . '|' . microtime(true) . '|' . $index);
            $result = $this->retryLeadSend($lead, $college, $requestKey, 0, '', $batchId);

            if (!empty($result['success'])) {
                $successCount++;
            } else {
                $failedCount++;
            }

            $responses[] = (string) ($result['response'] ?? '');
        }

        $isSuccess = $failedCount === 0;
        $summaryStatus = $isSuccess ? 'success' : 'failed';
        $summaryResponse = $isSuccess
            ? 'Selected leads successfully sent to the college API.'
            : 'Failed to send selected leads. Please try again.';

        $this->storeSelectedLeadPushSummaryLog($leads, $college, $summaryStatus, $summaryResponse, count($leads));

        return [
            'success' => $isSuccess,
            'college_name' => (string) ($college['name'] ?? $college['id'] ?? ''),
            'total' => count($leads),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'response_message' => $summaryResponse,
            'responses' => $responses,
        ];
    }

    private function retryLeadSend(array $lead, array $college, string $requestKey, int $mappingConfigurationId, string $jobToken, string $batchId): array
    {
        $attempt = 0;
        $lastResult = [
            'success' => false,
            'response' => 'Lead push failed.',
        ];

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            $lastResult = $this->sendLeadApi($lead, $college);

            $this->storeLeadLog(
                $mappingConfigurationId,
                $jobToken,
                $batchId,
                $lead,
                (string) ($college['name'] ?? $college['id'] ?? ''),
                (string) ($college['api_endpoint'] ?? ''),
                $lastResult['success'] ? 'success' : 'failed',
                (string) ($lastResult['response'] ?? ''),
                $requestKey,
                $attempt
            );

            if ($lastResult['success']) {
                break;
            }
        }

        return $lastResult;
    }

    private function sendLeadApi(array $lead, array $college): array
    {
        $request = $this->buildLeadPushRequest($lead, (string) ($college['id'] ?? ''), $college);
        if (!($request['success'] ?? false)) {
            return [
                'success' => false,
                'response' => (string) ($request['response'] ?? 'Lead push failed.'),
            ];
        }

        $curl = curl_init($request['url']);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $request['payload'],
            CURLOPT_HTTPHEADER => $request['headers'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $curlError !== '') {
            return [
                'success' => false,
                'response' => $curlError !== '' ? $curlError : 'Unknown cURL error',
            ];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'response' => is_string($response) ? $response : '',
        ];
    }

    private function buildLeadPushRequest(array $lead, string $collegeId, array $college): array
    {
        $payload = $this->collegeLeadPayload($lead);
        $url = (string) ($college['api_endpoint'] ?? '');
        $headers = ['Content-Type: application/json'];

        switch ($collegeId) {
            case 'IBI':
                $url = 'https://api.nopaperforms.com/dataporting/578/career_mantra';
                break;
            case 'Sunstone':
                $url = 'https://hub-console-api.sunstone.in/lead/leadPush';
                if (($college['api_token'] ?? '') !== '') {
                    $headers[] = 'Authorization: Bearer ' . $college['api_token'];
                }
                break;
            case 'IILM':
                $url = 'https://api.nopaperforms.com/dataporting/377/career_mantra';
                break;
            case 'GNOIT':
                $url = 'https://api.nopaperforms.com/dataporting/19/career_mantra';
                break;
            case 'NITTE':
                $url = 'https://api.in5.nopaperforms.com/dataporting/5609/career_mantra';
                break;
            case 'KCM':
                $url = 'https://api.nopaperforms.com/dataporting/434/career_mantra';
                break;
            case 'GIBS':
                $url = 'https://api.nopaperforms.com/dataporting/374/career_mantra';
                break;
            case 'Alliance':
                $url = 'https://api.nopaperforms.com/dataporting/207/career_mantra';
                break;
            case 'KKMU':
                $url = 'https://api.nopaperforms.com/dataporting/692/career_mantra';
                break;
            case 'PPSU':
                $url = 'https://api.in5.nopaperforms.com/dataporting/5562/career_mantra';
                break;
            case 'PBS':
                $url = 'https://thirdpartyapi.extraaedge.com/api/SaveRequest/';
                break;
            case 'PCU':
                $url = $url !== '' ? $url : 'https://api.in8.nopaperforms.com/dataporting/5674/career_mantra';
                break;
            case 'Lexicon':
                $url = 'https://api.nopaperforms.com/dataporting/375/career_mantra';
                break;
            default:
                if ($url === '') {
                    return [
                        'success' => false,
                        'response' => 'Missing API endpoint for college ' . $collegeId,
                    ];
                }
                break;
        }

        $jsonPayload = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Length: ' . strlen($jsonPayload);

        return [
            'success' => true,
            'url' => $url,
            'payload' => $jsonPayload,
            'headers' => $headers,
        ];
    }

    private function collegeLeadPayload(array $lead): array
    {
        return [
            'name' => trim((string) ($lead['Name'] ?? '')),
            'email' => trim((string) ($lead['Email'] ?? '')),
            'mobile' => trim((string) ($lead['Mobile'] ?? $lead['Phone'] ?? '')),
            'state' => trim((string) ($lead['State'] ?? '')),
            'city' => trim((string) ($lead['City'] ?? '')),
            'course' => trim((string) ($lead['Course'] ?? '')),
        ];
    }

    private function rowMatchesRegionAndCourse(array $row, array $regions, array $courseValues): bool
    {
        $region = (string) ($row['Region'] ?? '');
        if (!in_array($region, $regions, true)) {
            return false;
        }

        if ($courseValues === []) {
            return true;
        }

        return in_array(trim((string) ($row['Course'] ?? '')), $courseValues, true);
    }

    private function updateConfigurationStatus(int $mappingConfigurationId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE lead_mapping_configurations
             SET status = :status
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'id' => $mappingConfigurationId,
        ]);
    }

    private function updateJobState(string $jobToken, string $status, string $extraSql = ''): void
    {
        $sql = 'UPDATE lead_mapping_jobs SET status = :status';
        if ($extraSql !== '') {
            $sql .= ', ' . $extraSql;
        }
        $sql .= ' WHERE job_token = :job_token';

        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'status' => $status,
            'job_token' => $jobToken,
        ]);
    }

    private function incrementJobCounters(string $jobToken, bool $success): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE lead_mapping_jobs
             SET processed_requests = processed_requests + 1,
                 success_count = success_count + :success_increment,
                 failed_count = failed_count + :failed_increment
             WHERE job_token = :job_token'
        );
        $statement->execute([
            'success_increment' => $success ? 1 : 0,
            'failed_increment' => $success ? 0 : 1,
            'job_token' => $jobToken,
        ]);
        $this->syncUploadFileStatusByJobToken($jobToken);
    }

    private function incrementProcessedLeads(string $jobToken): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE lead_mapping_jobs
             SET processed_leads = processed_leads + 1
             WHERE job_token = :job_token'
        );
        $statement->execute([
            'job_token' => $jobToken,
        ]);
    }

    private function ensureLeadMappingJobsColumns(PDO $connection): void
    {
        if (!$this->columnExists($connection, 'lead_mapping_jobs', 'processed_leads')) {
            $connection->exec(
                'ALTER TABLE lead_mapping_jobs
                 ADD COLUMN processed_leads INT NOT NULL DEFAULT 0 AFTER total_requests'
            );
        }

        if (!$this->columnExists($connection, 'lead_mapping_jobs', 'start_time')) {
            $connection->exec(
                'ALTER TABLE lead_mapping_jobs
                 ADD COLUMN start_time TIME DEFAULT NULL AFTER delay_seconds'
            );
        }

        if (!$this->columnExists($connection, 'lead_mapping_jobs', 'end_time')) {
            $connection->exec(
                'ALTER TABLE lead_mapping_jobs
                 ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time'
            );
        }

        if (!$this->columnExists($connection, 'lead_mapping_jobs', 'leads_json')) {
            $connection->exec(
                'ALTER TABLE lead_mapping_jobs
                 ADD COLUMN leads_json LONGTEXT DEFAULT NULL AFTER colleges_json'
            );
        }
    }

    private function ensureLeadMappingConfigurationColumns(PDO $connection): void
    {
        if (!$this->columnExists($connection, 'lead_mapping_configurations', 'course_conversion_json')) {
            $connection->exec(
                'ALTER TABLE lead_mapping_configurations
                 ADD COLUMN course_conversion_json JSON DEFAULT NULL AFTER selected_colleges'
            );
        }

        if (!$this->columnExists($connection, 'lead_mapping_configurations', 'specialization_conversion_json')) {
            $connection->exec(
                'ALTER TABLE lead_mapping_configurations
                 ADD COLUMN specialization_conversion_json JSON DEFAULT NULL AFTER course_conversion_json'
            );
        }
    }

    private function ensureLeadApiLogsColumns(PDO $connection): void
    {
        if (!$this->columnExists($connection, 'lead_api_logs', 'mobile')) {
            $connection->exec(
                'ALTER TABLE lead_api_logs
                 ADD COLUMN mobile VARCHAR(50) DEFAULT NULL AFTER email'
            );

            $connection->exec(
                'UPDATE lead_api_logs
                 SET mobile = phone
                 WHERE (mobile IS NULL OR TRIM(mobile) = "")
                   AND phone IS NOT NULL
                   AND TRIM(phone) <> ""'
            );
        }

        if (!$this->columnExists($connection, 'lead_api_logs', 'schema_json')) {
            $connection->exec(
                'ALTER TABLE lead_api_logs
                 ADD COLUMN schema_json JSON DEFAULT NULL AFTER attempt_no'
            );
        }

        if (!$this->columnExists($connection, 'lead_api_logs', 'api_url')) {
            $connection->exec(
                'ALTER TABLE lead_api_logs
                 ADD COLUMN api_url VARCHAR(255) DEFAULT NULL AFTER college_name'
            );
        }
    }

    private function ensureLeadPushLogsColumns(PDO $connection): void
    {
        $definitions = [
            'mapping_configuration_id' => 'ALTER TABLE lead_push_logs ADD COLUMN mapping_configuration_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER id',
            'job_token' => 'ALTER TABLE lead_push_logs ADD COLUMN job_token VARCHAR(120) DEFAULT NULL AFTER mapping_configuration_id',
            'batch_id' => 'ALTER TABLE lead_push_logs ADD COLUMN batch_id VARCHAR(100) DEFAULT NULL AFTER job_token',
            'college_name' => 'ALTER TABLE lead_push_logs ADD COLUMN college_name VARCHAR(190) DEFAULT NULL AFTER college_id',
            'api_url' => 'ALTER TABLE lead_push_logs ADD COLUMN api_url VARCHAR(255) DEFAULT NULL AFTER college_name',
            'course' => 'ALTER TABLE lead_push_logs ADD COLUMN course VARCHAR(190) DEFAULT NULL AFTER region',
            'specialization' => 'ALTER TABLE lead_push_logs ADD COLUMN specialization VARCHAR(190) DEFAULT NULL AFTER course',
            'total_records' => 'ALTER TABLE lead_push_logs ADD COLUMN total_records INT NOT NULL DEFAULT 1 AFTER specialization',
            'status' => 'ALTER TABLE lead_push_logs ADD COLUMN status VARCHAR(50) DEFAULT NULL AFTER total_records',
            'response_message' => 'ALTER TABLE lead_push_logs ADD COLUMN response_message LONGTEXT DEFAULT NULL AFTER api_status',
            'updated_at' => 'ALTER TABLE lead_push_logs ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];

        foreach ($definitions as $column => $sql) {
            if ($this->columnExists($connection, 'lead_push_logs', $column)) {
                continue;
            }

            $connection->exec($sql);
        }
    }

    private function ensureColleaguesColumns(PDO $connection): void
    {
        $definitions = [
            'recommended_source' => 'ALTER TABLE colleagues ADD COLUMN recommended_source VARCHAR(120) DEFAULT NULL AFTER api_token',
            'external_college_id' => 'ALTER TABLE colleagues ADD COLUMN external_college_id VARCHAR(100) DEFAULT NULL AFTER recommended_source',
        ];

        foreach ($definitions as $column => $sql) {
            if ($this->columnExists($connection, 'colleagues', $column)) {
                continue;
            }

            $connection->exec($sql);
        }
    }

    private function ensureLeadsMainTableColumns(PDO $connection): void
    {
        $definitions = [
            'mapping_configuration_id' => 'ALTER TABLE leads_main_table ADD COLUMN mapping_configuration_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER id',
            'job_token' => 'ALTER TABLE leads_main_table ADD COLUMN job_token VARCHAR(120) DEFAULT NULL AFTER mapping_configuration_id',
            'batch_id' => 'ALTER TABLE leads_main_table ADD COLUMN batch_id VARCHAR(100) DEFAULT NULL AFTER job_token',
            'source_lead_id' => 'ALTER TABLE leads_main_table ADD COLUMN source_lead_id VARCHAR(100) NOT NULL AFTER batch_id',
            'college_id' => 'ALTER TABLE leads_main_table ADD COLUMN college_id VARCHAR(100) DEFAULT NULL AFTER source_lead_id',
            'college_name' => 'ALTER TABLE leads_main_table ADD COLUMN college_name VARCHAR(190) DEFAULT NULL AFTER college_id',
            'mobile' => 'ALTER TABLE leads_main_table ADD COLUMN mobile VARCHAR(50) DEFAULT NULL AFTER email',
            'push_status' => 'ALTER TABLE leads_main_table ADD COLUMN push_status VARCHAR(50) NOT NULL DEFAULT "pending" AFTER region',
            'state' => 'ALTER TABLE leads_main_table ADD COLUMN state VARCHAR(120) DEFAULT NULL AFTER specialization',
            'city' => 'ALTER TABLE leads_main_table ADD COLUMN city VARCHAR(120) DEFAULT NULL AFTER state',
            'response_message' => 'ALTER TABLE leads_main_table ADD COLUMN response_message LONGTEXT DEFAULT NULL AFTER push_status',
        ];

        foreach ($definitions as $column => $sql) {
            if ($this->columnExists($connection, 'leads_main_table', $column)) {
                continue;
            }

            $connection->exec($sql);
        }

        if ($this->columnExists($connection, 'leads_main_table', 'mobile')
            && $this->columnExists($connection, 'leads_main_table', 'phone')) {
            $connection->exec(
                'UPDATE leads_main_table
                 SET mobile = phone
                 WHERE (mobile IS NULL OR TRIM(mobile) = "")
                   AND phone IS NOT NULL
                   AND TRIM(phone) <> ""'
            );
        }
    }

    private function columnExists(PDO $connection, string $tableName, string $columnName): bool
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasSuccessfulLog(string $requestKey): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM lead_api_logs
             WHERE request_key = :request_key
               AND status = :status'
        );
        $statement->execute([
            'request_key' => $requestKey,
            'status' => 'success',
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function findCollegeById(string $collegeId): ?array
    {
        foreach ($this->colleagueCatalogByRegion() as $region => $colleges) {
            foreach ((array) $colleges as $college) {
                if ((string) ($college['id'] ?? '') === $collegeId) {
                    $college['region'] = $region;

                    return $college;
                }
            }
        }

        return null;
    }

    private function seedDefaultColleagues(PDO $connection): void
    {
        $statement = $connection->prepare(
            'INSERT INTO colleagues (
                id, college_name, region, api_url, api_token, recommended_source, external_college_id
             ) VALUES (
                :id, :college_name, :region, :api_url, :api_token, :recommended_source, :external_college_id
             )
             ON DUPLICATE KEY UPDATE
                college_name = VALUES(college_name),
                region = VALUES(region),
                api_url = VALUES(api_url),
                api_token = VALUES(api_token),
                recommended_source = VALUES(recommended_source),
                external_college_id = VALUES(external_college_id)'
        );

        foreach (colleague_catalog() as $region => $colleges) {
            foreach ((array) $colleges as $college) {
                $statement->execute([
                    'id' => (string) ($college['id'] ?? ''),
                    'college_name' => (string) ($college['name'] ?? $college['id'] ?? ''),
                    'region' => (string) $region,
                    'api_url' => (string) ($college['api_endpoint'] ?? ''),
                    'api_token' => (string) ($college['api_token'] ?? ''),
                    'recommended_source' => $this->stringOrNull($college['recommended_source'] ?? null),
                    'external_college_id' => $this->stringOrNull($college['external_college_id'] ?? null),
                ]);
            }
        }
    }

    private function findMappingConfiguration(int $mappingConfigurationId): ?array
    {
        if ($mappingConfigurationId <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT *
             FROM lead_mapping_configurations
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $mappingConfigurationId,
        ]);

        $configuration = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($configuration) ? $configuration : null;
    }

    private function latestJobByBatch(string $batchId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM lead_mapping_jobs
             WHERE batch_id = :batch_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $statement->execute([
            'batch_id' => $batchId,
        ]);

        $job = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($job) ? $job : null;
    }

    private function duplicateMappingConfiguration(array $mappingConfiguration, int $totalLeads): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO lead_mapping_configurations (
                batch_id, selected_regions, mapping_column, selected_courses, selected_specialization, selected_colleges, course_conversion_json, specialization_conversion_json, total_leads, status
             ) VALUES (
                :batch_id, :selected_regions, :mapping_column, :selected_courses, :selected_specialization, :selected_colleges, :course_conversion_json, :specialization_conversion_json, :total_leads, :status
             )'
        );
        $statement->execute([
            'batch_id' => (string) ($mappingConfiguration['batch_id'] ?? ''),
            'selected_regions' => (string) ($mappingConfiguration['selected_regions'] ?? '[]'),
            'mapping_column' => (string) ($mappingConfiguration['mapping_column'] ?? 'Course'),
            'selected_courses' => (string) ($mappingConfiguration['selected_courses'] ?? '[]'),
            'selected_specialization' => $this->stringOrNull($mappingConfiguration['selected_specialization'] ?? null),
            'selected_colleges' => (string) ($mappingConfiguration['selected_colleges'] ?? '[]'),
            'course_conversion_json' => $this->stringOrNull($mappingConfiguration['course_conversion_json'] ?? null),
            'specialization_conversion_json' => $this->stringOrNull($mappingConfiguration['specialization_conversion_json'] ?? null),
            'total_leads' => max(0, $totalLeads),
            'status' => 'confirmed',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function fetchBatchRows(string $batchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT batch_id, lead_id, status, name, email, mobile, phone, course, state, city, lead_score, lead_origin, campaign, lead_stage, lead_status, country, instance, instance_date, email_verification, mobile_verification, device, specialization, campus, last_activity, form_initiated, paid_apps, enrollment, college, college_name, region, source_file, schema_json
             FROM leads
             WHERE batch_id = :batch_id
             ORDER BY id ASC'
        );
        $statement->execute([
            'batch_id' => $batchId,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $schema = new LeadSchemaService();

        return array_map(
            static fn (array $row): array => $schema->mapStoredRow($row, $batchId),
            is_array($rows) ? $rows : []
        );
    }

    private function syncUploadFileStatus(string $batchId, string $jobToken): void
    {
        if (trim($batchId) === '' || trim($jobToken) === '') {
            return;
        }

        $job = $this->findJobByToken($jobToken);
        if ($job === null) {
            return;
        }

        $status = $this->trackedFileStatusFromJob($job);
        (new UploadedLeadFile())->updateStatus(
            $batchId,
            $status,
            $jobToken,
            isset($job['processed_requests']) ? (int) $job['processed_requests'] : null,
            isset($job['success_count']) ? (int) $job['success_count'] : null,
            isset($job['failed_count']) ? (int) $job['failed_count'] : null
        );
    }

    private function syncUploadFileStatusByJobToken(string $jobToken): void
    {
        $job = $this->findJobByToken($jobToken);
        if ($job === null) {
            return;
        }

        $this->syncUploadFileStatus((string) ($job['batch_id'] ?? ''), $jobToken);
    }

    private function trackedFileStatusFromJob(array $job): string
    {
        $jobStatus = strtolower(trim((string) ($job['status'] ?? 'uploaded')));
        $totalRequests = max(0, (int) ($job['total_requests'] ?? 0));
        $processedRequests = max(0, (int) ($job['processed_requests'] ?? 0));
        $successCount = max(0, (int) ($job['success_count'] ?? 0));
        $failedCount = max(0, (int) ($job['failed_count'] ?? 0));

        if (in_array($jobStatus, ['queued', 'processing'], true)) {
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

    private function decodedLeadsFromJob(array $job): array
    {
        $encoded = $job['leads_json'] ?? null;
        if (!is_string($encoded) || trim($encoded) === '') {
            return [];
        }

        $decoded = json_decode($encoded, true);
        if (!is_array($decoded)) {
            return [];
        }

        return (new LeadSchemaService())->normalizePayloadRows($decoded, (string) ($job['batch_id'] ?? ''));
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function decodeJsonMap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function calculateTotalRequests(array $leads, array $assignmentsByRegion): int
    {
        $total = 0;

        foreach ($leads as $lead) {
            $region = (string) ($lead['Region'] ?? 'West / Others');
            $total += count($this->normalizeCollegeIds((array) ($assignmentsByRegion[$region] ?? [])));
        }

        return $total;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeTimeOrNull(mixed $value): ?string
    {
        $time = trim((string) ($value ?? ''));
        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        return null;
    }

    private function encodeJson(array $value): string
    {
        return (string) json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function encodeJsonValue(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
