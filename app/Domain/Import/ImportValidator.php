<?php

namespace App\Domain\Import;

class ImportValidator
{
    /** @var array<string> */
    private const REQUIRED_COLUMNS = [
        'id de persona',
        'hora',
    ];

    /**
     * Validate parsed CSV data.
     *
     * @param  array{headers: array<int, string>, rows: array<int, array<string, string>>}  $parsed
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $parsed): array
    {
        $errors = [];

        $errors = array_merge($errors, $this->validateColumns($parsed['headers']));

        if (count($errors) > 0) {
            return ['valid' => false, 'errors' => $errors];
        }

        if (count($parsed['rows']) === 0) {
            $errors[] = 'El archivo CSV no contiene filas de datos.';

            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($parsed['rows'] as $row) {
            $rowErrors = $this->validateRow($row);
            $errors = array_merge($errors, $rowErrors);
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function validateColumns(array $headers): array
    {
        $errors = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! in_array($column, $headers, true)) {
                $errors[] = "Columna requerida ausente: '{$column}'.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateRow(array $row): array
    {
        $errors = [];
        $lineNumber = $row['_line_number'] ?? '?';

        if (empty(ltrim(trim($row['id de persona'] ?? ''), "'"))) {
            $errors[] = "Fila {$lineNumber}: campo 'id de persona' vacío.";
        }

        $datetime = trim($row['hora'] ?? '');
        if (empty($datetime)) {
            $errors[] = "Fila {$lineNumber}: campo 'hora' vacío.";
        } elseif (! $this->isValidDateTime($datetime)) {
            $errors[] = "Fila {$lineNumber}: formato de fecha inválido '{$datetime}'.";
        }

        return $errors;
    }

    private function isValidDateTime(string $datetime): bool
    {
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $datetime);
            if ($parsed !== false && $parsed->format($format) === $datetime) {
                return true;
            }
        }

        return false;
    }
}
