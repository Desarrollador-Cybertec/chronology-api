<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\AttendanceResult;
use App\Domain\Attendance\OvertimeCalculator;
use App\Models\Shift;
use Carbon\Carbon;
use Tests\TestCase;

class OvertimeCalculatorTest extends TestCase
{
    private OvertimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OvertimeCalculator;
    }

    private function makeShift(array $overrides = []): Shift
    {
        return new Shift(array_merge([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'lunch_required' => false,
            'lunch_duration_minutes' => 0,
            'tolerance_minutes' => 10,
            'overtime_enabled' => true,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 240,
        ], $overrides));
    }

    public function test_no_overtime_when_disabled(): void
    {
        $shift = $this->makeShift(['overtime_enabled' => false]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 19:00:00'),
            workedMinutes: 660,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->overtimeMinutes);
    }

    public function test_no_overtime_below_minimum_block(): void
    {
        $shift = $this->makeShift(['overtime_min_block_minutes' => 60]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 17:30:00'),
            workedMinutes: 570,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->overtimeMinutes);
    }

    public function test_calculates_overtime_meeting_minimum_block(): void
    {
        $shift = $this->makeShift(['overtime_min_block_minutes' => 60]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 18:00:00'),
            workedMinutes: 600,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result->overtimeMinutes);
    }

    public function test_caps_at_max_daily_overtime(): void
    {
        $shift = $this->makeShift([
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 120,
        ]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 21:00:00'),
            workedMinutes: 780,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(120, $result->overtimeMinutes);
    }

    public function test_no_cap_when_max_is_zero(): void
    {
        $shift = $this->makeShift([
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
        ]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 21:00:00'),
            workedMinutes: 780,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(240, $result->overtimeMinutes);
    }

    public function test_splits_diurnal_overtime(): void
    {
        $shift = $this->makeShift(['overtime_min_block_minutes' => 60]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 18:00:00'),
            workedMinutes: 600,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result->overtimeMinutes);
        $this->assertEquals(60, $result->overtimeDiurnalMinutes);
        $this->assertEquals(0, $result->overtimeNocturnalMinutes);
    }

    public function test_splits_nocturnal_overtime(): void
    {
        $shift = $this->makeShift(['overtime_min_block_minutes' => 60]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 19:30:00'),
            workedMinutes: 690,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(150, $result->overtimeMinutes);
        $this->assertEquals(60, $result->overtimeDiurnalMinutes);
        $this->assertEquals(90, $result->overtimeNocturnalMinutes);
    }

    public function test_night_shift_overtime_is_all_after_shift_end(): void
    {
        $shift = $this->makeShift([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
            'overtime_min_block_minutes' => 60,
        ]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 22:00:00'),
            lastCheck: Carbon::parse('2026-01-16 08:00:00'),
            workedMinutes: 600,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(120, $result->overtimeMinutes);
        $this->assertEquals(120, $result->overtimeDiurnalMinutes);
        $this->assertEquals(0, $result->overtimeNocturnalMinutes);
    }

    public function test_no_overtime_when_absent(): void
    {
        $shift = $this->makeShift();
        $result = new AttendanceResult(
            status: 'absent',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->overtimeMinutes);
    }

    public function test_no_overtime_without_shift(): void
    {
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 20:00:00'),
            workedMinutes: 720,
            status: 'present',
            shift: null,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->overtimeMinutes);
    }

    public function test_overtime_with_lunch_shift(): void
    {
        $shift = $this->makeShift([
            'lunch_required' => true,
            'lunch_duration_minutes' => 60,
            'overtime_min_block_minutes' => 60,
        ]);

        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 18:00:00'),
            workedMinutes: 540,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result->overtimeMinutes);
    }
}
