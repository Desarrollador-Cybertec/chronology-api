<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\WorkTimeCalculator;
use App\Models\RawLog;
use App\Models\Shift;
use Carbon\Carbon;
use Tests\TestCase;

class WorkTimeCalculatorTest extends TestCase
{
    private WorkTimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WorkTimeCalculator;
    }

    private function makeShift(array $overrides = []): Shift
    {
        return new Shift(array_merge([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'tolerance_minutes' => 10,
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
        ], $overrides));
    }

    public function test_absent_when_no_logs(): void
    {
        $result = $this->calculator->calculate(collect());

        $this->assertEquals('absent', $result->status);
        $this->assertEquals(0, $result->workedMinutes);
    }

    public function test_incomplete_with_single_log(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
        ]);

        $result = $this->calculator->calculate($logs);

        $this->assertEquals('incomplete', $result->status);
    }

    public function test_present_with_two_logs(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs);

        $this->assertEquals('present', $result->status);
        $this->assertEquals(540, $result->workedMinutes);
    }

    public function test_deducts_lunch_minutes(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs, lunchMinutes: 60);

        $this->assertEquals(480, $result->workedMinutes);
    }

    public function test_early_arrival_clamped_to_shift_start(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00']);
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:45:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs, $shift, 0, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals(540, $result->workedMinutes);
        $this->assertEquals('07:45', $result->firstCheck->format('H:i'));
        $this->assertEquals('17:00', $result->lastCheck->format('H:i'));
    }

    public function test_on_time_arrival_no_clamping(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00']);
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs, $shift, 0, Carbon::parse('2026-01-15'));

        $this->assertEquals(540, $result->workedMinutes);
    }

    public function test_late_arrival_no_clamping(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00']);
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:30:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs, $shift, 0, Carbon::parse('2026-01-15'));

        $this->assertEquals(510, $result->workedMinutes);
    }

    public function test_no_clamping_without_shift(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:45:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs);

        $this->assertEquals(555, $result->workedMinutes);
    }

    public function test_no_clamping_without_date_reference(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00']);
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:45:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs, $shift);

        $this->assertEquals(555, $result->workedMinutes);
    }

    public function test_early_arrival_with_lunch_deduction(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00']);
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:30:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->calculator->calculate($logs, $shift, 60, Carbon::parse('2026-01-15'));

        $this->assertEquals(480, $result->workedMinutes);
    }
}
