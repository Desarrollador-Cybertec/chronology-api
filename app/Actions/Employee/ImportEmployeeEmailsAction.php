<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;

class ImportEmployeeEmailsAction
{
    public function execute(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle, 0, ',');
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), $header);

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
                $unmatched[] = $csvName;
            }
        }

        fclose($handle);

        return [
            'matched'   => $matched,
            'unmatched' => count($unmatched),
            'unmatched_names' => $unmatched,
        ];
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        return preg_replace('/\s+/', ' ', $name);
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
