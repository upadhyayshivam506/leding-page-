<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/db.php';

use Services\AuthService;

function ensure_api_authenticated(): void
{
    $auth = new AuthService();
    if (!$auth->check()) {
        throw new RuntimeException('Unauthorized request. Please login again.');
    }
}

function leads_api_regions(): array
{
    return ['North', 'South', 'East', 'West / Others'];
}

function normalize_region_name(string $region): string
{
    $region = trim($region);

    return in_array($region, leads_api_regions(), true) ? $region : 'West / Others';
}

function all_colleagues(): array
{
    $all = [];

    foreach (colleague_catalog() as $regionColleagues) {
        foreach ($regionColleagues as $id => $colleague) {
            $all[$id] = $colleague;
        }
    }

    $orderedIds = ['IBI', 'Sunstone', 'IILM', 'NITTE', 'KKMU', 'KCM', 'PPSU', 'GNOIT', 'PBS', 'DBUU', 'PCU', 'JKBS', 'GIBS', 'Alliance', 'Lexicon'];
    $ordered = [];

    foreach ($orderedIds as $id) {
        if (isset($all[$id])) {
            $ordered[] = $all[$id];
            unset($all[$id]);
        } else {
            $ordered[] = [
                'id' => $id,
                'name' => $id,
                'api_endpoint' => '',
                'api_token' => '',
            ];
        }
    }

    foreach ($all as $colleague) {
        $ordered[] = $colleague;
    }

    return $ordered;
}

function find_colleague(string $collegeId): ?array
{
    $collegeId = trim($collegeId);

    if ($collegeId === '') {
        return null;
    }

    foreach (all_colleagues() as $colleague) {
        if (($colleague['id'] ?? '') === $collegeId) {
            return $colleague;
        }
    }

    return null;
}
function getColleaguesByRegion(string $region): array
{
    $map = colleague_catalog();
    $normalizedRegion = normalize_region_name($region);

    return array_values($map[$normalizedRegion] ?? []);
}

function getSelectedColleagues(string $region, array $selectedIds): array
{
    $normalizedRegion = normalize_region_name($region);
    $selectedIds = array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $selectedIds), static fn (string $id): bool => $id !== ''));

    if ($selectedIds === [] || in_array('NONE', $selectedIds, true)) {
        return [];
    }

    $catalog = colleague_catalog();
    $regionColleagues = $catalog[$normalizedRegion] ?? [];
    $selected = [];

    foreach ($selectedIds as $selectedId) {
        if (isset($regionColleagues[$selectedId])) {
            $selected[] = $regionColleagues[$selectedId];
        }
    }

    return $selected;
}

function fetch_leads_by_region(string $batchId, string $region): array
{
    $statement = db()->prepare(
        'SELECT lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region
         FROM leads
         WHERE batch_id = :batch_id AND region = :region
         ORDER BY id ASC'
    );
    $statement->execute([
        'batch_id' => $batchId,
        'region' => normalize_region_name($region),
    ]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function fetch_all_leads(): array
{
    $statement = db()->query(
        'SELECT lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region
         FROM leads
         ORDER BY created_at DESC, id DESC'
    );
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}
function buildLeadPushRequest(array $lead, string $college_id, ?array $colleague = null): array
{
    $collegeId = trim($college_id);
    $leadState = (string) ($lead['state'] ?? '');
    $payload = [];
    $url = $colleague['api_endpoint'] ?? '';
    $headers = ['Content-Type: application/json'];

    switch ($collegeId) {
        case 'IBI':
            $url = 'https://api.nopaperforms.com/dataporting/578/career_mantra';
            $payload = [
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
            ];
            break;

        case 'Sunstone':
            $url = 'https://hub-console-api.sunstone.in/lead/leadPush';
            $payload = [
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'program' => (string) ($lead['course'] ?? ''),
                'utm_source' => 'Aff_4074Care',
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
            ];
            if (($colleague['api_token'] ?? '') !== '') {
                $headers[] = 'Authorization: Bearer ' . $colleague['api_token'];
            }
            break;

        case 'IILM':
            $url = 'https://api.nopaperforms.com/dataporting/377/career_mantra';
            $payload = [
                'college_id' => '377',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'source' => 'career_mantra',
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'GNOIT':
            $url = 'https://api.nopaperforms.com/dataporting/19/career_mantra';
            $payload = [
                'college_id' => '19',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'country_dial_code' => '+91',
                'mobile' => (string) ($lead['phone'] ?? ''),
                'source' => 'career_mantra',
                'state' => $leadState,
                'Campus' => (string) ($lead['campus'] ?? ''),
                'city' => (string) ($lead['city'] ?? ''),
                'Course' => (string) ($lead['course'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'NITTE':
            $url = 'https://api.in5.nopaperforms.com/dataporting/5609/career_mantra';
            $payload = [
                'college_id' => '5609',
                'source' => 'career_mantra',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'specialization' => (string) ($lead['specialization'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'KCM':
            $url = 'https://api.nopaperforms.com/dataporting/434/career_mantra';
            $payload = [
                'college_id' => '434',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'source' => 'career_mantra',
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'GIBS':
            $url = 'https://api.nopaperforms.com/dataporting/374/career_mantra';
            $payload = [
                'college_id' => '374',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'country_dial_code' => '+91',
                'mobile' => (string) ($lead['phone'] ?? ''),
                'source' => 'career_mantra',
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'Alliance':
            $url = 'https://api.nopaperforms.com/dataporting/207/career_mantra';
            $payload = [
                'college_id' => '207',
                'source' => 'career_mantra',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'KKMU':
            $url = 'https://api.nopaperforms.com/dataporting/692/career_mantra';
            $payload = [
                'college_id' => '692',
                'source' => 'career_mantra',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
                'university_id' => 'UG',
                'course' => (string) ($lead['course'] ?? ''),
                'specialization' => (string) ($lead['specialization'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'PPSU':
            $url = 'https://api.in5.nopaperforms.com/dataporting/5562/career_mantra';
            $payload = [
                'college_id' => '5562',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'source' => 'career_mantra',
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'specialization' => (string) ($lead['specialization'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        case 'PBS':
            $url = 'https://thirdpartyapi.extraaedge.com/api/SaveRequest/';
            $payload = [
                'AuthToken' => (string) ($colleague['api_token'] ?? ''),
                'Source' => 'pcet',
                'FirstName' => (string) ($lead['name'] ?? ''),
                'Email' => (string) ($lead['email'] ?? ''),
                'State' => $leadState,
                'City' => (string) ($lead['city'] ?? ''),
                'MobileNumber' => (string) ($lead['phone'] ?? ''),
                'leadName' => 'Consultants',
                'LeadSource' => 'Mh. Alam_Career Mantra',
                'LeadCampaign' => 'Email',
                'LeadChannel' => 'Consultants',
                'Course' => (string) ($lead['course'] ?? ''),
                'Center' => 'PBS',
                'Location' => (string) ($lead['campus'] ?? 'PGDM'),
                'Entity4' => (string) ($lead['specialization'] ?? ''),
            ];
            break;

        case 'Lexicon':
            $url = 'https://api.nopaperforms.com/dataporting/375/career_mantra';
            $payload = [
                'college_id' => '375',
                'source' => 'career_mantra',
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'secret_key' => (string) ($colleague['api_token'] ?? ''),
            ];
            break;

        default:
            if ($url === '') {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'response' => 'Missing API endpoint for college_id ' . $collegeId,
                ];
            }

            $payload = [
                'lead_id' => (string) ($lead['lead_id'] ?? ''),
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'mobile' => (string) ($lead['phone'] ?? ''),
                'state' => $leadState,
                'city' => (string) ($lead['city'] ?? ''),
                'course' => (string) ($lead['course'] ?? ''),
                'specialization' => (string) ($lead['specialization'] ?? ''),
                'campus' => (string) ($lead['campus'] ?? ''),
                'college_name' => (string) ($lead['college_name'] ?? ''),
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

function createLeadPushHandle(array $request)
{
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

    return $curl;
}

function executeParallelLeadPushes(array $jobs, int $parallelLimit = 12): array
{
    if ($jobs === []) {
        return [];
    }

    $results = [];
    $chunks = array_chunk($jobs, max(1, $parallelLimit));

    foreach ($chunks as $chunk) {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($chunk as $index => $job) {
            $handle = createLeadPushHandle($job['request']);
            $handles[$index] = [
                'handle' => $handle,
                'job' => $job,
            ];
            curl_multi_add_handle($multiHandle, $handle);
        }

        $running = null;
        do {
            $multiExec = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($running > 0 && $multiExec === CURLM_OK);

        foreach ($handles as $entry) {
            $handle = $entry['handle'];
            $response = curl_multi_getcontent($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $curlError = curl_error($handle);

            $results[] = [
                'job' => $entry['job'],
                'success' => $response !== false && $curlError === '' && $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'response' => $curlError !== '' ? $curlError : (is_string($response) ? $response : ''),
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);
    }

    return $results;
}

function process_selected_colleagues_push(array $selectedCollegeIds): array
{
    $selectedCollegeIds = array_values(array_unique(array_filter(array_map(static fn ($id): string => trim((string) $id), $selectedCollegeIds), static fn (string $id): bool => $id !== '' && $id !== 'NONE')));
    if ($selectedCollegeIds === []) {
        throw new InvalidArgumentException('Please select at least one colleague.');
    }

    $selectedColleagues = [];
    foreach ($selectedCollegeIds as $collegeId) {
        $colleague = find_colleague($collegeId);
        if ($colleague === null) {
            throw new InvalidArgumentException('Please select a valid colleague.');
        }
        $selectedColleagues[$collegeId] = $colleague;
    }

    $leads = fetch_all_leads();
    $jobs = [];

    foreach ($leads as $lead) {
        foreach ($selectedColleagues as $collegeId => $colleague) {
            $request = buildLeadPushRequest($lead, $collegeId, $colleague);
            if (!($request['success'] ?? false)) {
                $jobs[] = [
                    'request' => [
                        'url' => '',
                        'payload' => '',
                        'headers' => [],
                    ],
                    'lead' => $lead,
                    'college_id' => $collegeId,
                    'region' => normalize_region_name((string) ($lead['region'] ?? 'West / Others')),
                    'precomputed_result' => $request,
                ];
                continue;
            }

            $jobs[] = [
                'request' => $request,
                'lead' => $lead,
                'college_id' => $collegeId,
                'region' => normalize_region_name((string) ($lead['region'] ?? 'West / Others')),
            ];
        }
    }

    $result = [
        'selected_colleagues' => array_values($selectedCollegeIds),
        'leads_processed' => count($leads),
        'api_calls' => count($jobs),
        'success_count' => 0,
        'failed_count' => 0,
        'details' => [],
    ];

    $readyJobs = array_values(array_filter($jobs, static fn (array $job): bool => !isset($job['precomputed_result'])));
    $responses = executeParallelLeadPushes($readyJobs);

    foreach ($jobs as $job) {
        $push = null;
        if (isset($job['precomputed_result'])) {
            $push = [
                'success' => false,
                'http_code' => (int) ($job['precomputed_result']['http_code'] ?? 0),
                'response' => (string) ($job['precomputed_result']['response'] ?? 'Lead push failed.'),
            ];
        } else {
            $push = array_shift($responses);
        }

        $status = ($push['success'] ?? false) ? 'success' : 'failed';
        $response = is_string($push['response'] ?? null) ? $push['response'] : json_encode($push);

        log_lead_push(
            (string) ($job['lead']['lead_id'] ?? ''),
            (string) $job['region'],
            (string) $job['college_id'],
            $status,
            $response
        );

        if ($status === 'success') {
            $result['success_count']++;
        } else {
            $result['failed_count']++;
        }

        $result['details'][] = [
            'lead_id' => (string) ($job['lead']['lead_id'] ?? ''),
            'region' => (string) $job['region'],
            'college_id' => (string) $job['college_id'],
            'status' => $status,
            'http_code' => (int) ($push['http_code'] ?? 0),
            'response' => $response,
        ];
    }

    return $result;
}
function pushLeadToAPI(array $lead, string $college_id, ?array $colleague = null): array
{
    $request = buildLeadPushRequest($lead, $college_id, $colleague);
    if (!($request['success'] ?? false)) {
        return [
            'success' => false,
            'http_code' => (int) ($request['http_code'] ?? 0),
            'response' => (string) ($request['response'] ?? 'Lead push failed.'),
        ];
    }

    $curl = createLeadPushHandle($request);
    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError !== '') {
        return [
            'success' => false,
            'http_code' => $httpCode,
            'response' => $curlError !== '' ? $curlError : 'Unknown cURL error',
        ];
    }

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => is_string($response) ? $response : '',
    ];
}

function log_lead_push(string $leadId, string $region, string $collegeId, string $status, string $response): void
{
    $statement = db()->prepare(
        'INSERT INTO lead_push_logs (lead_id, region, college_id, api_status, response)
         VALUES (:lead_id, :region, :college_id, :api_status, :response)'
    );
    $statement->execute([
        'lead_id' => $leadId,
        'region' => normalize_region_name($region),
        'college_id' => $collegeId,
        'api_status' => $status,
        'response' => $response,
    ]);
}

function log_region_assignment_skip(string $region, string $reason, int $leadsSkipped): void
{
    error_log((string) json_encode([
        'region' => normalize_region_name($region),
        'reason' => $reason,
        'leads_skipped' => $leadsSkipped,
        'timestamp' => date('c'),
    ]));
}

function process_all_leads_push(string $collegeId): array
{
    $selectedCollege = trim($collegeId);
    if ($selectedCollege === '') {
        throw new InvalidArgumentException('Please select a colleague.');
    }

    $colleague = find_colleague($selectedCollege);
    if ($colleague === null) {
        throw new InvalidArgumentException('Please select a valid colleague.');
    }

    $leads = fetch_all_leads();
    $result = [
        'college_id' => $selectedCollege,
        'leads_processed' => count($leads),
        'api_calls' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'details' => [],
    ];

    foreach ($leads as $lead) {
        $push = pushLeadToAPI($lead, $selectedCollege, $colleague);
        $status = $push['success'] ? 'success' : 'failed';
        $response = is_string($push['response'] ?? null) ? $push['response'] : json_encode($push);
        $region = normalize_region_name((string) ($lead['region'] ?? 'West / Others'));

        log_lead_push(
            (string) ($lead['lead_id'] ?? ''),
            $region,
            $selectedCollege,
            $status,
            $response
        );

        $result['api_calls']++;
        if ($push['success']) {
            $result['success_count']++;
        } else {
            $result['failed_count']++;
        }

        $result['details'][] = [
            'lead_id' => (string) ($lead['lead_id'] ?? ''),
            'region' => $region,
            'college_id' => $selectedCollege,
            'status' => $status,
            'http_code' => (int) ($push['http_code'] ?? 0),
            'response' => $response,
        ];
    }

    return $result;
}
function process_region_assignments(string $batchId, array $assignments): array
{
    if (trim($batchId) === '') {
        throw new InvalidArgumentException('Missing upload batch id.');
    }

    $result = [
        'regions_processed' => 0,
        'regions_skipped' => 0,
        'leads_processed' => 0,
        'api_calls' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'details' => [],
    ];

    foreach (leads_api_regions() as $region) {
        $normalizedRegion = normalize_region_name($region);
        $selectedIds = $assignments[$normalizedRegion] ?? [];
        $selectedIds = is_array($selectedIds) ? $selectedIds : [];
        $selectedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => trim((string) $id), $selectedIds),
            static fn (string $id): bool => $id !== '' && $id !== 'NONE'
        )));
        $leads = fetch_leads_by_region($batchId, $normalizedRegion);

        if ($leads === []) {
            $result['regions_skipped']++;
            $result['details'][] = [
                'region' => $normalizedRegion,
                'status' => 'skipped',
                'response' => 'Region skipped because it has no leads.',
            ];
            continue;
        }

        $availableColleagues = getColleaguesByRegion($normalizedRegion);
        if ($availableColleagues === []) {
            $leadsSkipped = count($leads);
            log_region_assignment_skip($normalizedRegion, 'No colleagues configured', $leadsSkipped);

            $result['regions_skipped']++;
            $result['details'][] = [
                'region' => $normalizedRegion,
                'status' => 'skipped',
                'leads_skipped' => $leadsSkipped,
                'response' => 'No colleagues configured for this region. Leads were not transferred.',
            ];
            continue;
        }

        if ($selectedIds === []) {
            throw new InvalidArgumentException('Please select at least one colleague for regions that have leads.');
        }

        $colleagues = getSelectedColleagues($normalizedRegion, $selectedIds);
        if ($colleagues === []) {
            throw new InvalidArgumentException('Please select at least one colleague for regions that have leads.');
        }

        $result['regions_processed']++;
        $result['leads_processed'] += count($leads);

        foreach ($leads as $lead) {
            foreach ($colleagues as $colleague) {
                $push = pushLeadToAPI($lead, (string) ($colleague['id'] ?? ''), $colleague);
                $status = $push['success'] ? 'success' : 'failed';
                $response = is_string($push['response'] ?? null) ? $push['response'] : json_encode($push);

                log_lead_push(
                    (string) ($lead['lead_id'] ?? ''),
                    $normalizedRegion,
                    (string) ($colleague['id'] ?? ''),
                    $status,
                    $response
                );

                $result['api_calls']++;
                if ($push['success']) {
                    $result['success_count']++;
                } else {
                    $result['failed_count']++;
                }

                $result['details'][] = [
                    'lead_id' => (string) ($lead['lead_id'] ?? ''),
                    'region' => $normalizedRegion,
                    'college_id' => (string) ($colleague['id'] ?? ''),
                    'status' => $status,
                    'http_code' => (int) ($push['http_code'] ?? 0),
                    'response' => $response,
                ];
            }
        }
    }

    return $result;
}




