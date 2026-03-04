<?php

namespace App\Domain\Import;

use Carbon\Carbon;

class RawLogNormalizer
{
    /**
     * Normalize a parsed CSV row into a standard structure.
     *
     * @param  array<string, string>  $row
     * @return array{external_employee_id: string, full_name: string, check_time: Carbon, date_reference: string, department: string|null, device: string|null, original_line: string}
     */
    public function normalize(array $row): array
    {
        $checkTime = $this->parseDateTime($row['hora'] ?? '');

        return [
            'external_employee_id' => ltrim(trim($row['id de persona'] ?? ''), "'"),
            'full_name' => trim($row['nombre'] ?? ''),
            'check_time' => $checkTime,
            'date_reference' => $checkTime->toDateString(),
            'department' => $this->normalizeOptional($row['departamento'] ?? null),
            'device' => $this->normalizeOptional($this->findDevice($row)),
            'original_line' => $row['_original_line'] ?? '',
        ];
    }

    /**
     * Find the device column value regardless of encoding artifacts in the column name.
     *
     * @param  array<string, string>  $row
     */
    private function findDevice(array $row): ?string
    {
        foreach (array_keys($row) as $key) {
            if (str_contains($key, 'verificaci') || str_contains($key, 'punto')) {
                return $row[$key] !== '-' ? $row[$key] : null;
            }
        }

        return null;
    }

    /**
     * Normalize all rows.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array{external_employee_id: string, check_time: Carbon, date_reference: string, department: string|null, device: string|null, original_line: string}>
     */
    public function normalizeAll(array $rows): array
    {
        return array_map(fn (array $row) => $this->normalize($row), $rows);
    }

    private function parseDateTime(string $datetime): Carbon
    {
        $datetime = trim($datetime);

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $datetime);
            } catch (\Exception) {
                continue;
            }
        }

        return Carbon::parse($datetime);
    }

    private function normalizeOptional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
