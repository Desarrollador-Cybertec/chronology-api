<?php

namespace App\Services;

use App\Domain\Import\CsvParser;
use App\Domain\Import\ImportValidator;
use App\Domain\Import\RawLogNormalizer;
use App\Jobs\ProcessImportBatchJob;
use App\Models\ImportBatch;
use App\Models\RawLog;

class ImportService
{
    public function __construct(
        private CsvParser $csvParser,
        private ImportValidator $importValidator,
        private RawLogNormalizer $rawLogNormalizer,
        private EmployeeResolverService $employeeResolver,
    ) {}

    /**
     * Process a CSV file for the given import batch.
     *
     * @return array{success: bool, errors: array<int, string>}
     */
    public function process(ImportBatch $batch, string $csvContent): array
    {
        $parsed = $this->parseCsv($csvContent);

        $validation = $this->validateCsv($parsed);

        if (! $validation['valid']) {
            $this->markBatchFailed($batch, $validation['errors']);

            return ['success' => false, 'errors' => $validation['errors']];
        }

        $normalizedRows = $this->normalizeRows($parsed['rows']);

        $batch->update([
            'status' => 'processing',
            'total_rows' => count($normalizedRows),
        ]);

        $this->storeRawLogs($batch, $normalizedRows);

        $this->dispatchProcessingJob($batch);

        return ['success' => true, 'errors' => []];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    private function parseCsv(string $content): array
    {
        return $this->csvParser->parse($content);
    }

    /**
     * @param  array{headers: array<int, string>, rows: array<int, array<string, string>>}  $parsed
     * @return array{valid: bool, errors: array<int, string>}
     */
    private function validateCsv(array $parsed): array
    {
        return $this->importValidator->validate($parsed);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array{external_employee_id: string, check_time: \Carbon\Carbon, date_reference: string, department: string|null, device: string|null, original_line: string}>
     */
    private function normalizeRows(array $rows): array
    {
        return $this->rawLogNormalizer->normalizeAll($rows);
    }

    /**
     * @param  array<int, array{external_employee_id: string, check_time: \Carbon\Carbon, date_reference: string, department: string|null, device: string|null, original_line: string}>  $normalizedRows
     */
    private function storeRawLogs(ImportBatch $batch, array $normalizedRows): void
    {
        $processedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($normalizedRows as $row) {
            try {
                $employee = $this->employeeResolver->resolve($row);

                RawLog::create([
                    'employee_id' => $employee->id,
                    'import_batch_id' => $batch->id,
                    'check_time' => $row['check_time'],
                    'date_reference' => $row['date_reference'],
                    'original_line' => $row['original_line'],
                ]);

                $processedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $errors[] = "Fila: {$row['original_line']} - Error: {$e->getMessage()}";
            }
        }

        $batch->update([
            'processed_rows' => $processedCount,
            'failed_rows' => $failedCount,
            'errors' => count($errors) > 0 ? $errors : null,
        ]);
    }

    private function dispatchProcessingJob(ImportBatch $batch): void
    {
        ProcessImportBatchJob::dispatch($batch);
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function markBatchFailed(ImportBatch $batch, array $errors): void
    {
        $batch->update([
            'status' => 'failed',
            'errors' => $errors,
        ]);
    }
}
