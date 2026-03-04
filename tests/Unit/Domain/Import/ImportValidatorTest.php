<?php

namespace Tests\Unit\Domain\Import;

use App\Domain\Import\ImportValidator;
use PHPUnit\Framework\TestCase;

class ImportValidatorTest extends TestCase
{
    private ImportValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImportValidator;
    }

    public function test_valid_csv_passes(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [
                ['id de persona' => "'1001", 'hora' => '2026-01-15 08:00:00', '_line_number' => 2, '_original_line' => "'1001,2026-01-15 08:00:00"],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_fails_when_id_column_missing(): void
    {
        $parsed = [
            'headers' => ['hora'],
            'rows' => [
                ['hora' => '2026-01-15 08:00:00', '_line_number' => 2, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertContains("Columna requerida ausente: 'id de persona'.", $result['errors']);
    }

    public function test_fails_when_datetime_column_missing(): void
    {
        $parsed = [
            'headers' => ['id de persona'],
            'rows' => [
                ['id de persona' => "'1001", '_line_number' => 2, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertContains("Columna requerida ausente: 'hora'.", $result['errors']);
    }

    public function test_fails_when_no_data_rows(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertContains('El archivo CSV no contiene filas de datos.', $result['errors']);
    }

    public function test_fails_when_id_is_empty(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [
                ['id de persona' => '', 'hora' => '2026-01-15 08:00:00', '_line_number' => 2, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertContains("Fila 2: campo 'id de persona' vacío.", $result['errors']);
    }

    public function test_fails_when_datetime_is_empty(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [
                ['id de persona' => "'1001", 'hora' => '', '_line_number' => 2, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertContains("Fila 2: campo 'hora' vacío.", $result['errors']);
    }

    public function test_fails_with_invalid_date_format(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [
                ['id de persona' => "'1001", 'hora' => 'not-a-date', '_line_number' => 2, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertContains("Fila 2: formato de fecha inválido 'not-a-date'.", $result['errors']);
    }

    public function test_accepts_alternative_date_formats(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [
                ['id de persona' => "'1001", 'hora' => '15/01/2026 08:00:00', '_line_number' => 2, '_original_line' => ''],
                ['id de persona' => "'1002", 'hora' => '2026-01-15 08:00', '_line_number' => 3, '_original_line' => ''],
                ['id de persona' => "'1003", 'hora' => '15/01/2026 08:00', '_line_number' => 4, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertTrue($result['valid']);
    }

    public function test_collects_multiple_row_errors(): void
    {
        $parsed = [
            'headers' => ['id de persona', 'hora'],
            'rows' => [
                ['id de persona' => '', 'hora' => 'bad-date', '_line_number' => 2, '_original_line' => ''],
                ['id de persona' => "'1001", 'hora' => '', '_line_number' => 3, '_original_line' => ''],
            ],
        ];

        $result = $this->validator->validate($parsed);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(3, count($result['errors']));
    }
}
