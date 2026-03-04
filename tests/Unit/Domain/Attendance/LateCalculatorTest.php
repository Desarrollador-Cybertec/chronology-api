<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\AttendanceResult;
use App\Domain\Attendance\LateCalculator;
use App\Models\Shift;
use Carbon\Carbon;
use Tests\TestCase;

class LateCalculatorTest extends TestCase
{
    private LateCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LateCalculator;
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
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
        ], $overrides));
    }

    public function test_no_late_when_on_time(): void
    {
        $shift = $this->makeShift();
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 17:00:00'),
            workedMinutes: 540,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->lateMinutes);
        $this->assertEquals(0, $result->earlyDepartureMinutes);
    }

    public function test_no_late_within_tolerance(): void
    {
        $shift = $this->makeShift(['tolerance_minutes' => 10]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:08:00'),
            lastCheck: Carbon::parse('2026-01-15 17:00:00'),
            workedMinutes: 532,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->lateMinutes);
    }

    public function test_calculates_late_minutes_beyond_tolerance(): void
    {
        $shift = $this->makeShift(['tolerance_minutes' => 10]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:25:00'),
            lastCheck: Carbon::parse('2026-01-15 17:00:00'),
            workedMinutes: 515,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(25, $result->lateMinutes);
    }

    public function test_calculates_early_departure(): void
    {
        $shift = $this->makeShift();
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:00:00'),
            lastCheck: Carbon::parse('2026-01-15 16:30:00'),
            workedMinutes: 510,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(30, $result->earlyDepartureMinutes);
    }

    public function test_both_late_and_early_departure(): void
    {
        $shift = $this->makeShift(['tolerance_minutes' => 5]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 08:20:00'),
            lastCheck: Carbon::parse('2026-01-15 16:40:00'),
            workedMinutes: 500,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(20, $result->lateMinutes);
        $this->assertEquals(20, $result->earlyDepartureMinutes);
    }

    public function test_no_calculation_without_shift(): void
    {
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 09:00:00'),
            lastCheck: Carbon::parse('2026-01-15 16:00:00'),
            workedMinutes: 420,
            status: 'present',
            shift: null,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->lateMinutes);
        $this->assertEquals(0, $result->earlyDepartureMinutes);
    }

    public function test_no_calculation_when_absent(): void
    {
        $shift = $this->makeShift();
        $result = new AttendanceResult(
            status: 'absent',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->lateMinutes);
        $this->assertEquals(0, $result->earlyDepartureMinutes);
    }

    public function test_night_shift_late(): void
    {
        $shift = $this->makeShift([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
            'tolerance_minutes' => 10,
        ]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 22:20:00'),
            lastCheck: Carbon::parse('2026-01-16 06:00:00'),
            workedMinutes: 460,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(20, $result->lateMinutes);
        $this->assertEquals(0, $result->earlyDepartureMinutes);
    }

    public function test_night_shift_early_departure(): void
    {
        $shift = $this->makeShift([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
        ]);
        $result = new AttendanceResult(
            firstCheck: Carbon::parse('2026-01-15 22:00:00'),
            lastCheck: Carbon::parse('2026-01-16 05:00:00'),
            workedMinutes: 420,
            status: 'present',
            shift: $shift,
        );

        $result = $this->calculator->calculate($result, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result->lateMinutes);
        $this->assertEquals(60, $result->earlyDepartureMinutes);
    }
}
