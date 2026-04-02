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
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY colleagues_region_index (region),
                KEY colleagues_college_name_index (college_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS lead_mapping_configurations (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                batch_id VARCHAR(100) NOT NULL,
                selected_regions JSON NOT NULL,
                mapping_column VARCHAR(100) NOT NULL,
                selected_courses JSON NOT NULL,
                selected_specialization VARCHAR(190) DEFAULT NULL,
                selected_colleges JSON NOT NULL,
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
                status VARCHAR(50) NOT NULL DEFAULT "queued",
                total_leads INT NOT NULL DEFAULT 0,
                total_requests INT NOT NULL DEFAULT 0,
                processed_leads INT NOT NULL DEFAULT 0,
                processed_requests INT NOT NULL DEFAULT 0,
                success_count INT NOT NULL DEFAULT 0,
                failed_count INT NOT NULL DEFAULT 0,
                colleges_json JSON NOT NULL,
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
                phone VARCHAR(50) DEFAULT NULL,
                course VARCHAR(190) DEFAULT NULL,
                specialization VARCHAR(190) DEFAULT NULL,
                campus VARCHAR(190) DEFAULT NULL,
                college_name VARCHAR(190) NOT NULL,
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
            'SELECT id, college_name, region, api_url, api_token
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
            ];
        }

        return $catalog;
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
        int $totalLeads
    ): int {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'INSERT INTO lead_mapping_configurations (
                batch_id, selected_regions, mapping_column, selected_courses, selected_specialization, selected_colleges, total_leads, status
             ) VALUES (
                :batch_id, :selected_regions, :mapping_column, :selected_courses, :selected_specialization, :selected_colleges, :total_leads, :status
             )'
        );

        $statement->execute([
            'batch_id' => $batchId,
            'selected_regions' => $this->encodeJson($regions),
            'mapping_column' => trim($mappingColumn) !== '' ? $mappingColumn : 'Course',
            'selected_courses' => $this->encodeJson($courses),
            'selected_specialization' => $specialization !== '' ? $specialization : null,
            'selected_colleges' => $this->encodeJson($collegeIds),
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

    public function createOrReuseJob(int $mappingConfigurationId, string $batchId, int $batchSize, float $delay, array $leads, array $assignmentsByRegion): array
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
                mapping_configuration_id, batch_id, job_token, batch_size, delay_seconds, status, total_leads, total_requests, colleges_json
             ) VALUES (
                :mapping_configuration_id, :batch_id, :job_token, :batch_size, :delay_seconds, :status, :total_leads, :total_requests, :colleges_json
             )'
        );
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId,
            'batch_id' => $batchId,
            'job_token' => $jobToken,
            'batch_size' => $batchSize,
            'delay_seconds' => number_format($delay, 2, '.', ''),
            'status' => 'queued',
            'total_leads' => count($leads),
            'total_requests' => $totalRequests,
            'colleges_json' => $this->encodeJsonValue($assignmentsByRegion),
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

        $regions = $this->decodeJsonArray($mappingConfiguration['selected_regions'] ?? '[]');
        $courses = $this->decodeJsonArray($mappingConfiguration['selected_courses'] ?? '[]');
        $specialization = trim((string) ($mappingConfiguration['selected_specialization'] ?? ''));
        $assignmentsByRegion = $this->decodeJsonMap($latestJob['colleges_json'] ?? '{}');
        $rows = $this->fetchBatchRows($batchId);
        $leads = $this->filterPreviewRows($rows, $regions, $courses, $specialization);

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
            $assignmentsByRegion
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

        $regions = $this->decodeJsonArray($mappingConfiguration['selected_regions'] ?? '[]');
        $courses = $this->decodeJsonArray($mappingConfiguration['selected_courses'] ?? '[]');
        $assignmentsByRegion = $this->decodeJsonMap($job['colleges_json'] ?? '{}');
        $specialization = trim((string) ($mappingConfiguration['selected_specialization'] ?? ''));
        $rows = $this->fetchBatchRows((string) ($mappingConfiguration['batch_id'] ?? ''));
        $leads = $this->filterPreviewRows($rows, $regions, $courses, $specialization);

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
                        $this->storeLeadLog($mappingConfigurationId, $jobToken, $batchId, $lead, $collegeId, 'failed', 'College configuration not found.', $requestKey, 1);
                        $this->incrementJobCounters($jobToken, false);
                        continue;
                    }

                    $result = $this->retryLeadSend($lead, $college, $requestKey, $mappingConfigurationId, $jobToken, $batchId);
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
        string $status,
        string $response,
        string $requestKey,
        int $attemptNo
    ): void {
        $this->ensureTables();

        $statement = Database::connection()->prepare(
            'INSERT INTO lead_api_logs (
                mapping_configuration_id, job_token, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, status, response, request_key, attempt_no, schema_json, created_at
             ) VALUES (
                :mapping_configuration_id, :job_token, :batch_id, :lead_id, :name, :email, :phone, :course, :specialization, :campus, :college_name, :city, :state, :region, :source_file, :status, :response, :request_key, :attempt_no, :schema_json, NOW()
             )'
        );
        $statement->execute([
            'mapping_configuration_id' => $mappingConfigurationId > 0 ? $mappingConfigurationId : null,
            'job_token' => $jobToken !== '' ? $jobToken : null,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'lead_id' => (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? ''),
            'name' => $this->stringOrNull($lead['Name'] ?? null),
            'email' => $this->stringOrNull($lead['Email'] ?? null),
            'phone' => $this->stringOrNull($lead['Mobile'] ?? $lead['Phone'] ?? null),
            'course' => $this->stringOrNull($lead['Course'] ?? null),
            'specialization' => $this->stringOrNull($lead['Specialization'] ?? null),
            'campus' => $this->stringOrNull($lead['Campus'] ?? null),
            'college_name' => $collegeName,
            'city' => $this->stringOrNull($lead['City'] ?? null),
            'state' => $this->stringOrNull($lead['State'] ?? null),
            'region' => $this->stringOrNull($lead['Region'] ?? null),
            'source_file' => $this->stringOrNull($lead['Source File'] ?? null),
            'status' => $status,
            'response' => $response,
            'request_key' => $requestKey !== '' ? $requestKey : null,
            'attempt_no' => max(1, $attemptNo),
            'schema_json' => (string) json_encode((new LeadSchemaService())->visibleRow($lead, (string) ($lead['Batch ID'] ?? $batchId)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
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
        $leadState = (string) ($lead['State'] ?? '');
        $payload = [];
        $url = (string) ($college['api_endpoint'] ?? '');
        $headers = ['Content-Type: application/json'];

        switch ($collegeId) {
            case 'IBI':
                $url = 'https://api.nopaperforms.com/dataporting/578/career_mantra';
                $payload = [
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                ];
                break;
            case 'Sunstone':
                $url = 'https://hub-console-api.sunstone.in/lead/leadPush';
                $payload = [
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'program' => (string) ($lead['Course'] ?? ''),
                    'utm_source' => 'Aff_4074Care',
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                ];
                if (($college['api_token'] ?? '') !== '') {
                    $headers[] = 'Authorization: Bearer ' . $college['api_token'];
                }
                break;
            case 'IILM':
                $url = 'https://api.nopaperforms.com/dataporting/377/career_mantra';
                $payload = [
                    'college_id' => '377',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'source' => 'career_mantra',
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'GNOIT':
                $url = 'https://api.nopaperforms.com/dataporting/19/career_mantra';
                $payload = [
                    'college_id' => '19',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'country_dial_code' => '+91',
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'source' => 'career_mantra',
                    'state' => $leadState,
                    'Campus' => (string) ($lead['Campus'] ?? ''),
                    'city' => (string) ($lead['City'] ?? ''),
                    'Course' => (string) ($lead['Course'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'NITTE':
                $url = 'https://api.in5.nopaperforms.com/dataporting/5609/career_mantra';
                $payload = [
                    'college_id' => '5609',
                    'source' => 'career_mantra',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'specialization' => (string) ($lead['Specialization'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'KCM':
                $url = 'https://api.nopaperforms.com/dataporting/434/career_mantra';
                $payload = [
                    'college_id' => '434',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'source' => 'career_mantra',
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'GIBS':
                $url = 'https://api.nopaperforms.com/dataporting/374/career_mantra';
                $payload = [
                    'college_id' => '374',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'country_dial_code' => '+91',
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'source' => 'career_mantra',
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'Alliance':
                $url = 'https://api.nopaperforms.com/dataporting/207/career_mantra';
                $payload = [
                    'college_id' => '207',
                    'source' => 'career_mantra',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'KKMU':
                $url = 'https://api.nopaperforms.com/dataporting/692/career_mantra';
                $payload = [
                    'college_id' => '692',
                    'source' => 'career_mantra',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                    'university_id' => 'UG',
                    'course' => (string) ($lead['Course'] ?? ''),
                    'specialization' => (string) ($lead['Specialization'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'PPSU':
                $url = 'https://api.in5.nopaperforms.com/dataporting/5562/career_mantra';
                $payload = [
                    'college_id' => '5562',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'source' => 'career_mantra',
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'specialization' => (string) ($lead['Specialization'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            case 'PBS':
                $url = 'https://thirdpartyapi.extraaedge.com/api/SaveRequest/';
                $payload = [
                    'AuthToken' => (string) ($college['api_token'] ?? ''),
                    'Source' => 'pcet',
                    'FirstName' => (string) ($lead['Name'] ?? ''),
                    'Email' => (string) ($lead['Email'] ?? ''),
                    'State' => $leadState,
                    'City' => (string) ($lead['City'] ?? ''),
                    'MobileNumber' => (string) ($lead['Mobile'] ?? ''),
                    'leadName' => 'Consultants',
                    'LeadSource' => 'Mh. Alam_Career Mantra',
                    'LeadCampaign' => 'Email',
                    'LeadChannel' => 'Consultants',
                    'Course' => (string) ($lead['Course'] ?? ''),
                    'Center' => 'PBS',
                    'Location' => (string) ($lead['Campus'] ?? 'PGDM'),
                    'Entity4' => (string) ($lead['Specialization'] ?? ''),
                ];
                break;
            case 'Lexicon':
                $url = 'https://api.nopaperforms.com/dataporting/375/career_mantra';
                $payload = [
                    'college_id' => '375',
                    'source' => 'career_mantra',
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'secret_key' => (string) ($college['api_token'] ?? ''),
                ];
                break;
            default:
                if ($url === '') {
                    return [
                        'success' => false,
                        'response' => 'Missing API endpoint for college ' . $collegeId,
                    ];
                }

                $payload = [
                    'lead_id' => (string) ($lead['__lead_id'] ?? $lead['Lead ID'] ?? ''),
                    'name' => (string) ($lead['Name'] ?? ''),
                    'email' => (string) ($lead['Email'] ?? ''),
                    'mobile' => (string) ($lead['Mobile'] ?? ''),
                    'state' => $leadState,
                    'city' => (string) ($lead['City'] ?? ''),
                    'course' => (string) ($lead['Course'] ?? ''),
                    'specialization' => (string) ($lead['Specialization'] ?? ''),
                    'campus' => (string) ($lead['Campus'] ?? ''),
                    'college_name' => (string) ($lead['College'] ?? ''),
                ];
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
        $statement = $connection->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => 'lead_mapping_jobs',
            'column_name' => 'processed_leads',
        ]);

        if ((int) $statement->fetchColumn() === 0) {
            $connection->exec(
                'ALTER TABLE lead_mapping_jobs
                 ADD COLUMN processed_leads INT NOT NULL DEFAULT 0 AFTER total_requests'
            );
        }
    }

    private function ensureLeadApiLogsColumns(PDO $connection): void
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => 'lead_api_logs',
            'column_name' => 'schema_json',
        ]);

        if ((int) $statement->fetchColumn() === 0) {
            $connection->exec(
                'ALTER TABLE lead_api_logs
                 ADD COLUMN schema_json JSON DEFAULT NULL AFTER attempt_no'
            );
        }
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
        $count = (int) $connection->query('SELECT COUNT(*) FROM colleagues')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $statement = $connection->prepare(
            'INSERT INTO colleagues (
                id, college_name, region, api_url, api_token
             ) VALUES (
                :id, :college_name, :region, :api_url, :api_token
             )'
        );

        foreach (colleague_catalog() as $region => $colleges) {
            foreach ((array) $colleges as $college) {
                $statement->execute([
                    'id' => (string) ($college['id'] ?? ''),
                    'college_name' => (string) ($college['name'] ?? $college['id'] ?? ''),
                    'region' => (string) $region,
                    'api_url' => (string) ($college['api_endpoint'] ?? ''),
                    'api_token' => (string) ($college['api_token'] ?? ''),
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
                batch_id, selected_regions, mapping_column, selected_courses, selected_specialization, selected_colleges, total_leads, status
             ) VALUES (
                :batch_id, :selected_regions, :mapping_column, :selected_courses, :selected_specialization, :selected_colleges, :total_leads, :status
             )'
        );
        $statement->execute([
            'batch_id' => (string) ($mappingConfiguration['batch_id'] ?? ''),
            'selected_regions' => (string) ($mappingConfiguration['selected_regions'] ?? '[]'),
            'mapping_column' => (string) ($mappingConfiguration['mapping_column'] ?? 'Course'),
            'selected_courses' => (string) ($mappingConfiguration['selected_courses'] ?? '[]'),
            'selected_specialization' => $this->stringOrNull($mappingConfiguration['selected_specialization'] ?? null),
            'selected_colleges' => (string) ($mappingConfiguration['selected_colleges'] ?? '[]'),
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

    private function encodeJson(array $value): string
    {
        return (string) json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function encodeJsonValue(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
