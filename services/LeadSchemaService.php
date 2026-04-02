<?php

declare(strict_types=1);

namespace Services;

final class LeadSchemaService
{
    private const STANDARD_COLUMNS = [
        'Batch ID',
        'Status',
        'Name',
        'Email',
        'Mobile',
        'Course',
        'State',
        'City',
        'Lead Score',
        'Lead Origin',
        'Campaign',
        'Lead Stage',
        'Lead Status',
        'Country',
        'Instance',
        'Instance Date',
        'Email Verification',
        'Mobile Verification',
        'Device',
        'Specialization',
        'Campus',
        'Last Activity',
        'Form Initiated',
        'Paid Apps',
        'Enrollment',
        'College',
    ];

    private const COLUMN_ALIASES = [
        'Batch ID' => ['batch_id', 'batchid'],
        'Status' => ['status'],
        'Name' => ['name', 'student_name', 'full_name'],
        'Email' => ['email', 'email_address'],
        'Mobile' => ['mobile', 'mobile_number', 'phone', 'phone_number', 'contact'],
        'Course' => ['course', 'program'],
        'State' => ['state', 'province'],
        'City' => ['city'],
        'Lead Score' => ['lead_score', 'score'],
        'Lead Origin' => ['lead_origin', 'origin', 'source', 'lead_source'],
        'Campaign' => ['campaign', 'campaign_name', 'utm_campaign'],
        'Lead Stage' => ['lead_stage', 'stage'],
        'Lead Status' => ['lead_status'],
        'Country' => ['country', 'country_name'],
        'Instance' => ['instance'],
        'Instance Date' => ['instance_date', 'instance_datetime', 'instance_created_at'],
        'Email Verification' => ['email_verification', 'email_verified', 'email_validation'],
        'Mobile Verification' => ['mobile_verification', 'mobile_verified', 'phone_verification', 'phone_verified'],
        'Device' => ['device', 'device_type'],
        'Specialization' => ['specialization'],
        'Campus' => ['campus', 'college_campus'],
        'Last Activity' => ['last_activity', 'activity', 'last_activity_at'],
        'Form Initiated' => ['form_initiated', 'form_started'],
        'Paid Apps' => ['paid_apps', 'paid_application', 'paid_applications'],
        'Enrollment' => ['enrollment', 'enrollment_status'],
        'College' => ['college', 'college_name'],
    ];

    public function columns(): array
    {
        return self::STANDARD_COLUMNS;
    }

    public function emptyRow(string $batchId = ''): array
    {
        $row = array_fill_keys(self::STANDARD_COLUMNS, '');
        $row['Batch ID'] = $batchId;

        return $row;
    }

    public function mapIncomingRow(array $rawRow, int $index, string $batchId): array
    {
        $row = $this->emptyRow($batchId);

        foreach (self::COLUMN_ALIASES as $column => $aliases) {
            $value = $this->firstValue($rawRow, $aliases);
            if ($value !== '') {
                $row[$column] = $value;
            }
        }

        $row['Batch ID'] = $batchId;

        $row['__lead_id'] = $this->internalLeadId($rawRow, $index);
        $row['Region'] = getRegionByState($row['State']);
        $row['Source File'] = '';

        return $row;
    }

    public function mapStoredRow(array $row, string $defaultBatchId = ''): array
    {
        $schemaRow = $this->emptyRow($defaultBatchId);

        $storedSchema = $row['schema_json'] ?? $row['schema_data'] ?? null;
        if (is_string($storedSchema) && trim($storedSchema) !== '') {
            $decoded = json_decode($storedSchema, true);
            if (is_array($decoded)) {
                foreach (self::STANDARD_COLUMNS as $column) {
                    $schemaRow[$column] = trim((string) ($decoded[$column] ?? ''));
                }
            }
        }

        $fallbackMap = [
            'Batch ID' => (string) ($row['batch_id'] ?? $row['Batch ID'] ?? $defaultBatchId),
            'Status' => (string) ($row['status'] ?? $row['Status'] ?? ''),
            'Name' => (string) ($row['name'] ?? $row['Name'] ?? ''),
            'Email' => (string) ($row['email'] ?? $row['Email'] ?? ''),
            'Mobile' => (string) ($row['mobile'] ?? $row['phone'] ?? $row['Mobile'] ?? $row['Phone'] ?? ''),
            'Course' => (string) ($row['course'] ?? $row['Course'] ?? ''),
            'State' => (string) ($row['state'] ?? $row['State'] ?? ''),
            'City' => (string) ($row['city'] ?? $row['City'] ?? ''),
            'Lead Score' => (string) ($row['lead_score'] ?? $row['Lead Score'] ?? ''),
            'Lead Origin' => (string) ($row['lead_origin'] ?? $row['Lead Origin'] ?? ''),
            'Campaign' => (string) ($row['campaign'] ?? $row['Campaign'] ?? ''),
            'Lead Stage' => (string) ($row['lead_stage'] ?? $row['Lead Stage'] ?? ''),
            'Lead Status' => (string) ($row['lead_status'] ?? $row['Lead Status'] ?? ''),
            'Country' => (string) ($row['country'] ?? $row['Country'] ?? ''),
            'Instance' => (string) ($row['instance'] ?? $row['Instance'] ?? ''),
            'Instance Date' => (string) ($row['instance_date'] ?? $row['Instance Date'] ?? ''),
            'Email Verification' => (string) ($row['email_verification'] ?? $row['Email Verification'] ?? ''),
            'Mobile Verification' => (string) ($row['mobile_verification'] ?? $row['Mobile Verification'] ?? ''),
            'Device' => (string) ($row['device'] ?? $row['Device'] ?? ''),
            'Specialization' => (string) ($row['specialization'] ?? $row['Specialization'] ?? ''),
            'Campus' => (string) ($row['campus'] ?? $row['Campus'] ?? ''),
            'Last Activity' => (string) ($row['last_activity'] ?? $row['Last Activity'] ?? ''),
            'Form Initiated' => (string) ($row['form_initiated'] ?? $row['Form Initiated'] ?? ''),
            'Paid Apps' => (string) ($row['paid_apps'] ?? $row['Paid Apps'] ?? ''),
            'Enrollment' => (string) ($row['enrollment'] ?? $row['Enrollment'] ?? ''),
            'College' => (string) ($row['college'] ?? $row['college_name'] ?? $row['College'] ?? $row['College Name'] ?? ''),
        ];

        foreach ($fallbackMap as $column => $value) {
            if ($schemaRow[$column] === '') {
                $schemaRow[$column] = trim($value);
            }
        }

        $schemaRow['__lead_id'] = trim((string) ($row['lead_id'] ?? $row['Lead ID'] ?? $row['__lead_id'] ?? ''));
        if ($schemaRow['__lead_id'] === '') {
            $schemaRow['__lead_id'] = 'LD' . (string) (1001 + random_int(0, 999999));
        }

        $schemaRow['Region'] = trim((string) ($row['region'] ?? $row['Region'] ?? getRegionByState($schemaRow['State'])));
        if ($schemaRow['Region'] === '') {
            $schemaRow['Region'] = getRegionByState($schemaRow['State']);
        }

        $schemaRow['Source File'] = trim((string) ($row['source_file'] ?? $row['Source File'] ?? ''));

        return $schemaRow;
    }

    public function databaseRowFromSchema(array $row): array
    {
        $schemaRow = $this->mapStoredRow($row, (string) ($row['Batch ID'] ?? $row['batch_id'] ?? ''));
        $mobile = $schemaRow['Mobile'];
        $college = $schemaRow['College'];

        return [
            'lead_id' => $schemaRow['__lead_id'],
            'status' => $schemaRow['Status'],
            'name' => $schemaRow['Name'],
            'email' => $schemaRow['Email'],
            'mobile' => $mobile,
            'phone' => $mobile,
            'course' => $schemaRow['Course'],
            'state' => $schemaRow['State'],
            'city' => $schemaRow['City'],
            'lead_score' => $schemaRow['Lead Score'],
            'lead_origin' => $schemaRow['Lead Origin'],
            'campaign' => $schemaRow['Campaign'],
            'lead_stage' => $schemaRow['Lead Stage'],
            'lead_status' => $schemaRow['Lead Status'],
            'country' => $schemaRow['Country'],
            'instance' => $schemaRow['Instance'],
            'instance_date' => $schemaRow['Instance Date'],
            'email_verification' => $schemaRow['Email Verification'],
            'mobile_verification' => $schemaRow['Mobile Verification'],
            'device' => $schemaRow['Device'],
            'specialization' => $schemaRow['Specialization'],
            'campus' => $schemaRow['Campus'],
            'last_activity' => $schemaRow['Last Activity'],
            'form_initiated' => $schemaRow['Form Initiated'],
            'paid_apps' => $schemaRow['Paid Apps'],
            'enrollment' => $schemaRow['Enrollment'],
            'college' => $college,
            'college_name' => $college,
            'region' => (string) ($schemaRow['Region'] ?? getRegionByState($schemaRow['State'])),
            'schema_json' => $this->encodeSchemaRow($schemaRow),
        ];
    }

    public function normalizePayloadRows(array $rows, string $defaultBatchId = ''): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = $this->mapStoredRow($row, $defaultBatchId);
        }

        return $normalized;
    }

    public function visibleRow(array $row, string $defaultBatchId = ''): array
    {
        $normalized = $this->mapStoredRow($row, $defaultBatchId);
        $visible = [];

        foreach (self::STANDARD_COLUMNS as $column) {
            $visible[$column] = (string) ($normalized[$column] ?? '');
        }

        return $visible;
    }

    public function hasVisibleContent(array $row): bool
    {
        foreach (self::STANDARD_COLUMNS as $column) {
            if ($column === 'Batch ID') {
                continue;
            }

            if (trim((string) ($row[$column] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function encodeSchemaRow(array $row): string
    {
        $payload = [];
        foreach (self::STANDARD_COLUMNS as $column) {
            $payload[$column] = (string) ($row[$column] ?? '');
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function internalLeadId(array $row, int $index): string
    {
        $leadId = $this->firstValue($row, ['lead_id', 'leadid', 'id', 'reference_id']);

        return $leadId !== '' ? $leadId : 'LD' . (string) (1001 + $index);
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
}
