<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\LunchAnalyzer;
use App\Models\RawLog;
use App\Models\Shift;
use Carbon\Carbon;
use Tests\TestCase;

class LunchAnalyzerTest extends TestCase
{
    private LunchAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new LunchAnalyzer;
    }

    public function test_returns_zero_when_lunch_not_required(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => false,
            'lunch_start_time' => null,
            'lunch_end_time' => null,
            'lunch_duration_minutes' => 0,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result);
    }

    public function test_returns_zero_for_single_log(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result);
    }

    public function test_returns_configured_duration_when_no_lunch_times_defined(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => null,
            'lunch_end_time' => null,
            'lunch_duration_minutes' => 60,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result);
    }

    public function test_detects_actual_lunch_break_from_four_punches(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
            new RawLog(['check_time' => '2026-01-15 13:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result);
    }

    public function test_detects_extended_lunch_break(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        // Employee took 90 min lunch (12:00 - 13:30)
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
            new RawLog(['check_time' => '2026-01-15 13:30:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(90, $result);
    }

    public function test_falls_back_to_configured_duration_with_two_punches(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result);
    }

    public function test_returns_zero_when_shift_ends_before_lunch(): void
    {
        $shift = new Shift([
            'start_time' => '06:00',
            'end_time' => '11:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 06:00:00']),
            new RawLog(['check_time' => '2026-01-15 11:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result);
    }

    public function test_returns_zero_when_shift_starts_after_lunch(): void
    {
        $shift = new Shift([
            'start_time' => '14:00',
            'end_time' => '22:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 14:00:00']),
            new RawLog(['check_time' => '2026-01-15 22:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result);
    }

    public function test_returns_zero_for_empty_logs(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $result = $this->analyzer->analyze(collect(), $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result);
    }

    public function test_detects_short_lunch_break(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        // Employee took only 30 min lunch (12:00 - 12:30)
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:30:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(30, $result);
    }
}
