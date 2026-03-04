<?php

namespace Tests\Unit\Domain\Import;

use App\Domain\Import\RawLogNormalizer;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RawLogNormalizerTest extends TestCase
{
    private RawLogNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new RawLogNormalizer;
    }

    public function test_normalizes_single_row(): void
    {
        $row = [
            'id de persona' => "'3",
            'nombre' => 'CLAUDIA ZULAY TORRA GOMEZ',
            'departamento' => 'InsummaBG',
            'hora' => '2026-01-15 08:05:00',
            'punto de verificación de asistencia' => 'Cafeteria Principal_Puerta1',
            '_original_line' => "'3,CLAUDIA ZULAY TORRA GOMEZ,InsummaBG,2026-01-15 08:05:00",
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertEquals('3', $result['external_employee_id']);
        $this->assertEquals('CLAUDIA ZULAY TORRA GOMEZ', $result['full_name']);
        $this->assertInstanceOf(Carbon::class, $result['check_time']);
        $this->assertEquals('2026-01-15', $result['date_reference']);
        $this->assertEquals('InsummaBG', $result['department']);
        $this->assertEquals('Cafeteria Principal_Puerta1', $result['device']);
    }

    public function test_strips_leading_single_quote_from_id(): void
    {
        $row = [
            'id de persona' => "'1",
            'hora' => '2026-01-06 09:00:21',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertEquals('1', $result['external_employee_id']);
    }

    public function test_normalizes_all_rows(): void
    {
        $rows = [
            ['id de persona' => "'1001", 'nombre' => 'JUAN PEREZ', 'hora' => '2026-01-15 08:00:00', '_original_line' => 'line1', '_line_number' => 2],
            ['id de persona' => "'1002", 'nombre' => 'MARIA GOMEZ', 'hora' => '2026-01-15 08:10:00', '_original_line' => 'line2', '_line_number' => 3],
        ];

        $result = $this->normalizer->normalizeAll($rows);

        $this->assertCount(2, $result);
        $this->assertEquals('1001', $result[0]['external_employee_id']);
        $this->assertEquals('1002', $result[1]['external_employee_id']);
    }

    public function test_handles_missing_optional_columns(): void
    {
        $row = [
            'id de persona' => "'1001",
            'hora' => '2026-01-15 08:00:00',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertNull($result['department']);
        $this->assertNull($result['device']);
        $this->assertEquals('', $result['full_name']);
    }

    public function test_handles_empty_string_department(): void
    {
        $row = [
            'id de persona' => "'1001",
            'hora' => '2026-01-15 08:00:00',
            'departamento' => '  ',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertNull($result['department']);
    }

    public function test_dash_device_value_is_normalized_to_null(): void
    {
        $row = [
            'id de persona' => "'1001",
            'hora' => '2026-01-15 08:00:00',
            'punto de verificación de asistencia' => '-',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertNull($result['device']);
    }

    public function test_finds_device_with_encoding_artifact_in_key(): void
    {
        $row = [
            'id de persona' => "'1001",
            'hora' => '2026-01-15 08:00:00',
            'punto de verificaci?n de asistencia' => 'Puerta Principal',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertEquals('Puerta Principal', $result['device']);
    }

    public function test_trims_external_employee_id(): void
    {
        $row = [
            'id de persona' => "  '1001  ",
            'hora' => '2026-01-15 08:00:00',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertEquals('1001', $result['external_employee_id']);
    }

    public function test_parses_date_correctly(): void
    {
        $row = [
            'id de persona' => "'1001",
            'hora' => '2026-01-06 09:00:21',
            '_original_line' => '',
            '_line_number' => 2,
        ];

        $result = $this->normalizer->normalize($row);

        $this->assertEquals('2026-01-06', $result['date_reference']);
        $this->assertEquals('09:00:21', $result['check_time']->format('H:i:s'));
    }
}
