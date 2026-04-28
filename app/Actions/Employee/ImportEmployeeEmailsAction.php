<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;

class ImportEmployeeEmailsAction
{
    /**
     * Score mínimo para aceptar un match.
     * Se aplica solo cuando hay ≥2 tokens; nombres de 1 sola palabra requieren 0.85.
     */
    private const THRESHOLD      = 0.52;
    private const SINGLE_THRESHOLD = 0.85;

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
            'model'  => $e,
            'tokens' => $this->tokenize($e->full_name),
            'norm'   => $this->normalize($e->full_name),
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

            $csvTokens = $this->tokenize($csvName);
            $csvNorm   = $this->normalize($csvName);

            if (empty($csvTokens)) {
                continue;
            }

            $best      = null;
            $bestScore = 0.0;

            foreach ($normalizedEmployees as $entry) {
                $score = $this->scoreMatch($csvTokens, $csvNorm, $entry['tokens'], $entry['norm']);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best      = $entry['model'];
                }
            }

            $threshold = count($csvTokens) === 1 ? self::SINGLE_THRESHOLD : self::THRESHOLD;

            if ($best && $bestScore >= $threshold) {
                $best->update(['email' => $email]);
                $matched++;
            } else {
                $unmatched[] = $this->safeUtf8($csvName);
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
     * Score combinado: token-by-token + string completo + bonus por primer token.
     *
     * • Token score: cada token del nombre más corto busca su mejor pareja
     *   en el nombre más largo → promedio. Tolera palabras extra (apellidos
     *   adicionales) sin penalizar.
     * • String score: similar_text sobre el string completo normalizado.
     * • Bonus: si el primer token de ambos coincide ≥90 % se suma +0.10
     *   para priorizar matches donde el primer nombre es igual.
     */
    private function scoreMatch(array $tokensA, string $normA, array $tokensB, string $normB): float
    {
        [$shorter, $longer] = count($tokensA) <= count($tokensB)
            ? [$tokensA, $tokensB]
            : [$tokensB, $tokensA];

        // Token score
        $tokenScore = 0.0;
        foreach ($shorter as $word) {
            $best = 0.0;
            foreach ($longer as $candidate) {
                similar_text($word, $candidate, $pct);
                $phonetic = $this->phoneticScore($word, $candidate);
                $wordScore = max($pct / 100, $phonetic);
                if ($wordScore > $best) {
                    $best = $wordScore;
                }
            }
            $tokenScore += $best;
        }
        $tokenScore /= count($shorter);

        // String score
        similar_text($normA, $normB, $fullPct);
        $fullScore = $fullPct / 100;

        $score = max($tokenScore, $fullScore);

        // Bonus: primer nombre muy similar en ambas listas
        if (! empty($tokensA) && ! empty($tokensB)) {
            similar_text($tokensA[0], $tokensB[0], $firstPct);
            if ($firstPct >= 90) {
                $score = min(1.0, $score + 0.10);
            }
        }

        return $score;
    }

    /**
     * Score fonético usando soundex: 1.0 si igual soundex, 0.5 si primer char igual, 0 si no.
     */
    private function phoneticScore(string $a, string $b): float
    {
        if (strlen($a) < 2 || strlen($b) < 2) {
            return 0.0;
        }

        if (soundex($a) === soundex($b)) {
            return 0.92;
        }

        if (metaphone($a) === metaphone($b)) {
            return 0.88;
        }

        return 0.0;
    }

    /**
     * Normaliza: minúsculas → iconv TRANSLIT (convierte tildes a ASCII) → espacios simples.
     * NO stripea bytes antes de iconv para no corromper multibyte UTF-8.
     */
    private function normalize(string $name): string
    {
        $name   = mb_strtolower(trim($name));
        $result = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        // Eliminar caracteres no alfanuméricos excepto espacios
        $result = preg_replace('/[^a-z0-9 ]/i', '', (string) $result);

        return preg_replace('/\s+/', ' ', trim($result));
    }

    /**
     * Palabras normalizadas de longitud > 1.
     */
    private function tokenize(string $name): array
    {
        $words = explode(' ', $this->normalize($name));

        return array_values(array_filter($words, fn ($w) => strlen($w) > 1));
    }

    /**
     * Limpia el campo email: elimina espacios, saltos de línea, BOM.
     * Si hay varios emails (// o ,) toma el primero válido.
     */
    private function cleanEmail(string $raw): string
    {
        $email = trim(preg_replace('/[\r\n\x{FEFF}]/u', '', $raw));

        foreach (preg_split('/[\/,]+/', $email) as $part) {
            $part = trim($part);
            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                return strtolower($part);
            }
        }

        return strtolower($email);
    }

    /**
     * Convierte a UTF-8 si el string no es UTF-8 válido (viene en Windows-1252).
     */
    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * Garantiza que el string sea UTF-8 válido para incluirlo en la respuesta JSON.
     */
    private function safeUtf8(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
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
