<?php

namespace Tests\Unit\Domain\Import;

use App\Domain\Import\CsvParser;
use PHPUnit\Framework\TestCase;

class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CsvParser;
    }

    public function test_parses_valid_csv(): void
    {
        $csv = "id,datetime,department\n1001,2026-01-15 08:05:00,Ventas\n1002,2026-01-15 08:10:00,IT";

        $result = $this->parser->parse($csv);

        $this->assertEquals(['id', 'datetime', 'department'], $result['headers']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('1001', $result['rows'][0]['id']);
        $this->assertEquals('2026-01-15 08:05:00', $result['rows'][0]['datetime']);
        $this->assertEquals('Ventas', $result['rows'][0]['department']);
    }

    public function test_headers_are_lowercased_and_trimmed(): void
    {
        $csv = " ID , DateTime , Department \n1001,2026-01-15 08:00:00,IT";

        $result = $this->parser->parse($csv);

        $this->assertEquals(['id', 'datetime', 'department'], $result['headers']);
    }

    public function test_skips_empty_lines(): void
    {
        $csv = "id,datetime\n1001,2026-01-15 08:00:00\n\n1002,2026-01-15 09:00:00\n";

        $result = $this->parser->parse($csv);

        $this->assertCount(2, $result['rows']);
    }

    public function test_skips_rows_with_wrong_column_count(): void
    {
        $csv = "id,datetime\n1001,2026-01-15 08:00:00\n1002";

        $result = $this->parser->parse($csv);

        $this->assertCount(1, $result['rows']);
    }

    public function test_returns_empty_for_header_only_csv(): void
    {
        $csv = 'id,datetime';

        $result = $this->parser->parse($csv);

        $this->assertEmpty($result['headers']);
        $this->assertCount(0, $result['rows']);
    }

    public function test_returns_empty_for_empty_input(): void
    {
        $result = $this->parser->parse('');

        $this->assertCount(0, $result['headers']);
        $this->assertCount(0, $result['rows']);
    }

    public function test_adds_line_number_and_original_line_metadata(): void
    {
        $csv = "id,datetime\n1001,2026-01-15 08:00:00\n1002,2026-01-15 09:00:00";

        $result = $this->parser->parse($csv);

        $this->assertEquals(2, $result['rows'][0]['_line_number']);
        $this->assertEquals(3, $result['rows'][1]['_line_number']);
        $this->assertEquals('1001,2026-01-15 08:00:00', $result['rows'][0]['_original_line']);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $csv = "id,datetime\r\n1001,2026-01-15 08:00:00\r\n1002,2026-01-15 09:00:00";

        $result = $this->parser->parse($csv);

        $this->assertCount(2, $result['rows']);
    }

    public function test_trims_cell_values(): void
    {
        $csv = "id,datetime\n  1001 , 2026-01-15 08:00:00 ";

        $result = $this->parser->parse($csv);

        $this->assertEquals('1001', $result['rows'][0]['id']);
        $this->assertEquals('2026-01-15 08:00:00', $result['rows'][0]['datetime']);
    }
}
