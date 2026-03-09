<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\LunchAnalyzer;
use App\Models\RawLog;
use App\Models\Shift;
use App\Models\ShiftBreak;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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

    public function test_deducts_all_break_blocks_when_no_punches_detected(): void
    {
        $shift = new Shift([
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);

        $shift->setRelation('breaks', new Collection([
            new ShiftBreak(['type' => 'morning_snack', 'start_time' => '09:30', 'end_time' => '09:45', 'duration_minutes' => 15, 'position' => 0]),
            new ShiftBreak(['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'position' => 1]),
            new ShiftBreak(['type' => 'afternoon_snack', 'start_time' => '15:00', 'end_time' => '15:15', 'duration_minutes' => 15, 'position' => 2]),
        ]));

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:00:00']),
            new RawLog(['check_time' => '2026-01-15 16:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result);
    }

    public function test_detects_actual_break_punch_and_uses_real_duration(): void
    {
        $shift = new Shift([
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);

        $shift->setRelation('breaks', new Collection([
            new ShiftBreak(['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'position' => 0]),
        ]));

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:25:00']),
            new RawLog(['check_time' => '2026-01-15 16:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(25, $result);
    }

    public function test_skips_break_blocks_outside_work_period(): void
    {
        $shift = new Shift([
            'start_time' => '07:00',
            'end_time' => '12:00',
        ]);

        $shift->setRelation('breaks', new Collection([
            new ShiftBreak(['type' => 'morning_snack', 'start_time' => '09:30', 'end_time' => '09:45', 'duration_minutes' => 15, 'position' => 0]),
            new ShiftBreak(['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'position' => 1]),
            new ShiftBreak(['type' => 'afternoon_snack', 'start_time' => '15:00', 'end_time' => '15:15', 'duration_minutes' => 15, 'position' => 2]),
        ]));

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:00:00']),
            new RawLog(['check_time' => '2026-01-15 11:55:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(15, $result);
    }

    public function test_break_blocks_with_empty_breaks_falls_back_to_legacy(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        $shift->setRelation('breaks', new Collection);

        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(60, $result);
    }

    public function test_multiple_breaks_with_mixed_detection(): void
    {
        $shift = new Shift([
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);

        $shift->setRelation('breaks', new Collection([
            new ShiftBreak(['type' => 'morning_snack', 'start_time' => '09:30', 'end_time' => '09:45', 'duration_minutes' => 15, 'position' => 0]),
            new ShiftBreak(['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'position' => 1]),
        ]));

        // Only lunch punched, morning snack falls back to configured duration
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:02:00']),
            new RawLog(['check_time' => '2026-01-15 12:28:00']),
            new RawLog(['check_time' => '2026-01-15 16:00:00']),
        ]);

        $result = $this->analyzer->analyze($logs, $shift, Carbon::parse('2026-01-15'));

        // morning_snack: 15 (configured, no punch detected near 09:30)
        // lunch: 26 (actual punch 12:02 to 12:28)
        $this->assertEquals(41, $result);
    }

    public function test_returns_zero_with_empty_logs_and_break_blocks(): void
    {
        $shift = new Shift([
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);

        $shift->setRelation('breaks', new Collection([
            new ShiftBreak(['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'position' => 0]),
        ]));

        $result = $this->analyzer->analyze(collect(), $shift, Carbon::parse('2026-01-15'));

        $this->assertEquals(0, $result);
    }
}
