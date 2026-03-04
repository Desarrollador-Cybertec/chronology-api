<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\AttendanceDayBuilder;
use App\Models\RawLog;
use App\Models\Shift;
use Tests\TestCase;

class AttendanceDayBuilderTest extends TestCase
{
    private AttendanceDayBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new AttendanceDayBuilder;
    }

    public function test_returns_absent_for_empty_logs(): void
    {
        $result = $this->builder->build(collect());

        $this->assertEquals('absent', $result->status);
        $this->assertNull($result->firstCheck);
        $this->assertNull($result->lastCheck);
        $this->assertEquals(0, $result->workedMinutes);
    }

    public function test_returns_incomplete_for_single_log(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
        ]);

        $result = $this->builder->build($logs);

        $this->assertEquals('incomplete', $result->status);
        $this->assertNotNull($result->firstCheck);
        $this->assertEquals(0, $result->workedMinutes);
    }

    public function test_calculates_worked_minutes(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->builder->build($logs);

        $this->assertEquals('present', $result->status);
        $this->assertEquals(540, $result->workedMinutes);
        $this->assertEquals('08:00', $result->firstCheck->format('H:i'));
        $this->assertEquals('17:00', $result->lastCheck->format('H:i'));
    }

    public function test_deducts_lunch_from_worked_minutes(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'lunch_required' => true,
            'lunch_duration_minutes' => 60,
            'tolerance_minutes' => 10,
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->builder->build($logs, $shift);

        $this->assertEquals(480, $result->workedMinutes);
    }

    public function test_handles_no_shift(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->builder->build($logs, null);

        $this->assertEquals('present', $result->status);
        $this->assertEquals(540, $result->workedMinutes);
        $this->assertNull($result->shift);
    }

    public function test_attaches_shift_to_result(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'lunch_required' => false,
            'lunch_duration_minutes' => 0,
            'tolerance_minutes' => 10,
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->builder->build($logs, $shift);

        $this->assertSame($shift, $result->shift);
    }

    public function test_absent_with_shift_still_returns_shift(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'lunch_required' => false,
            'lunch_duration_minutes' => 0,
            'tolerance_minutes' => 10,
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
        ]);

        $result = $this->builder->build(collect(), $shift);

        $this->assertEquals('absent', $result->status);
        $this->assertSame($shift, $result->shift);
    }
}
