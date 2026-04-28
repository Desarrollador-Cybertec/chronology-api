<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;

class ImportEmployeeEmailsAction
{
    public function execute(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        // Detectar y saltar BOM UTF-8 si existe
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ',');
        $header = array_map(fn ($h) => mb_strtolower(trim($this->toUtf8($h))), $header);

        $emailCol = $this->findColumn($header, ['correo electronico', 'correo electrónico', 'email', 'correo']);
        $nameCol  = $this->findColumn($header, ['usuario', 'nombre', 'nombre completo', 'name']);

        $employees = Employee::query()->where('is_active', true)->get();

        $normalizedEmployees = $employees->map(fn (Employee $e) => [
            'model'      => $e,
            'normalized' => $this->normalize($e->full_name),
        ]);

        $matched   = 0;
        $unmatched = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // Convertir toda la fila a UTF-8
            $row = array_map(fn ($cell) => $this->toUtf8($cell), $row);

            if (count($row) <= max($emailCol, $nameCol)) {
                continue;
            }

            $email    = trim($row[$emailCol]);
            $csvName  = trim($row[$nameCol]);
            $normName = $this->normalize($csvName);

            if (! $email || ! $csvName) {
                continue;
            }

            $best      = null;
            $bestScore = 0;

            foreach ($normalizedEmployees as $entry) {
                similar_text($normName, $entry['normalized'], $pct);
                if ($pct > $bestScore) {
                    $bestScore = $pct;
                    $best      = $entry['model'];
                }
            }

            if ($best && $bestScore >= 80) {
                $best->update(['email' => $email]);
                $matched++;
            } else {
                // Garantizar que el nombre sea UTF-8 válido antes de incluirlo en la respuesta
                $unmatched[] = mb_convert_encoding($csvName, 'UTF-8', 'UTF-8');
            }
        }

        fclose($handle);

        return [
            'matched'         => $matched,
            'unmatched'       => count($unmatched),
            'unmatched_names' => $unmatched,
        ];
    }

    /**
     * Convierte un string a UTF-8 detectando automáticamente si viene en Windows-1252/Latin-1.
     */
    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // Quitar tildes y caracteres especiales
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        return preg_replace('/\s+/', ' ', (string) $name);
    }

    private function findColumn(array $header, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return 0;
    }
}
