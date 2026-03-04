<?php

namespace App\Domain\Import;

class CsvParser
{
    /**
     * Parse raw CSV content into an array of associative rows.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);

        $rows = [];
        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line);

            if (count($values) !== count($headers)) {
                continue;
            }

            $row = array_combine($headers, array_map('trim', $values));
            $row['_line_number'] = $index + 2; // 1-based, header is line 1
            $row['_original_line'] = $line;
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
