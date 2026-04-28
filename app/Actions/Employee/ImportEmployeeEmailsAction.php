<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;

class ImportEmployeeEmailsAction
{
    /**
     * Mínimo score promedio de tokens para considerar un match válido.
     * 0.72 = cada palabra del nombre corto debe coincidir al ~72% con alguna del nombre largo.
     */
    private const TOKEN_THRESHOLD = 0.72;

    public function execute(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        // Saltar BOM UTF-8 si existe
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
            'tokens'     => $this->tokenize($e->full_name),
            'normalized' => $this->normalize($e->full_name),
        ]);

        $matched   = 0;
        $unmatched = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $row = array_map(fn ($cell) => $this->toUtf8($cell), $row);

            if (count($row) <= max($emailCol, $nameCol)) {
                continue;
            }

            $email   = $this->cleanEmail($row[$emailCol]);
            $csvName = trim($row[$nameCol]);

            if (! $email || ! $csvName) {
                continue;
            }

            $csvTokens     = $this->tokenize($csvName);
            $csvNormalized = $this->normalize($csvName);

            if (empty($csvTokens)) {
                continue;
            }

            $best      = null;
            $bestScore = 0.0;

            foreach ($normalizedEmployees as $entry) {
                $score = $this->scoreMatch($csvTokens, $csvNormalized, $entry['tokens'], $entry['normalized']);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best      = $entry['model'];
                }
            }

            if ($best && $bestScore >= self::TOKEN_THRESHOLD) {
                $best->update(['email' => $email]);
                $matched++;
            } else {
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
     * Score combinado: token-by-token + string completo. Toma el mayor.
     *
     * Token score: por cada token del nombre más corto, busca el mejor
     * match entre los tokens del nombre más largo. El score final es
     * el promedio de esos mejores matches. Esto permite que "Karolay Martinez"
     * encuentre a "KAROLAY ANDREA MARTINEZ SERRANO" con score ~1.0.
     */
    private function scoreMatch(array $tokensA, string $normA, array $tokensB, string $normB): float
    {
        // Usar el más corto como referencia para no penalizar palabras extra
        [$shorter, $longer] = count($tokensA) <= count($tokensB)
            ? [$tokensA, $tokensB]
            : [$tokensB, $tokensA];

        $tokenScore = 0.0;

        foreach ($shorter as $word) {
            $best = 0.0;
            foreach ($longer as $candidate) {
                similar_text($word, $candidate, $pct);
                if ($pct > $best) {
                    $best = $pct;
                }
            }
            $tokenScore += $best / 100;
        }

        $tokenScore = $tokenScore / count($shorter);

        // Score de string completo como fallback para nombres de una sola palabra
        similar_text($normA, $normB, $fullPct);
        $fullScore = $fullPct / 100;

        return max($tokenScore, $fullScore);
    }

    /**
     * Normaliza: minúsculas, sin tildes, sin caracteres especiales, espacios simples.
     */
    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // Reemplazar caracteres corruptos comunes por encoding incorrecto
        $name = preg_replace('/[^\x20-\x7E]/', '', $name);
        $result = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        return preg_replace('/\s+/', ' ', trim((string) $result));
    }

    /**
     * Devuelve array de palabras normalizadas, filtrando palabras de 1 letra.
     */
    private function tokenize(string $name): array
    {
        $normalized = $this->normalize($name);
        $words      = explode(' ', $normalized);

        return array_values(array_filter($words, fn ($w) => strlen($w) > 1));
    }

    /**
     * Limpia el campo email: elimina espacios, saltos de línea, BOM, y toma
     * el primer email si hay varios separados por // o coma.
     */
    private function cleanEmail(string $raw): string
    {
        $email = trim(preg_replace('/[\r\n\x{FEFF}]/u', '', $raw));

        // Si hay múltiples emails (separados por // o ,), tomar el primero válido
        foreach (preg_split('/[\/,]+/', $email) as $part) {
            $part = trim($part);
            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                return $part;
            }
        }

        // Retornar aunque no pase validación estricta (podría tener dominio interno)
        return strtolower($email);
    }

    /**
     * Convierte a UTF-8 desde Windows-1252 si el string no es UTF-8 válido.
     */
    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
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
