<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\LogReducer;
use App\Models\RawLog;
use Tests\TestCase;

class LogReducerTest extends TestCase
{
    private LogReducer $reducer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reducer = new LogReducer;
    }

    public function test_returns_empty_collection_for_no_logs(): void
    {
        $result = $this->reducer->reduce(collect());

        $this->assertCount(0, $result);
    }

    public function test_returns_single_log_unchanged(): void
    {
        $log = new RawLog(['check_time' => '2026-01-15 08:00:00']);

        $result = $this->reducer->reduce(collect([$log]));

        $this->assertCount(1, $result);
    }

    public function test_returns_two_logs_unchanged(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
        ]);

        $result = $this->reducer->reduce($logs);

        $this->assertCount(2, $result);
    }

    public function test_reduces_noise_cluster_to_first_only(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 08:02:00']),
            new RawLog(['check_time' => '2026-01-15 08:05:00']),
            new RawLog(['check_time' => '2026-01-15 08:10:00']),
        ]);

        $result = $this->reducer->reduce($logs);

        $this->assertCount(1, $result);
        $this->assertEquals('2026-01-15 08:00:00', $result->first()->check_time->format('Y-m-d H:i:s'));
    }

    public function test_preserves_separate_clusters(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 08:03:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:02:00']),
        ]);

        $result = $this->reducer->reduce($logs);

        $this->assertCount(2, $result);
        $this->assertEquals('08:00', $result[0]->check_time->format('H:i'));
        $this->assertEquals('17:00', $result[1]->check_time->format('H:i'));
    }

    public function test_handles_custom_noise_window(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 08:20:00']),
            new RawLog(['check_time' => '2026-01-15 09:00:00']),
            new RawLog(['check_time' => '2026-01-15 09:20:00']),
        ]);

        $resultWide = $this->reducer->reduce($logs, 60);
        $this->assertCount(2, $resultWide);

        $resultNarrow = $this->reducer->reduce($logs, 10);
        $this->assertCount(4, $resultNarrow);
    }

    public function test_sorts_unsorted_input(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
        ]);

        $result = $this->reducer->reduce($logs);

        $this->assertEquals('08:00', $result->first()->check_time->format('H:i'));
        $this->assertEquals('17:00', $result->last()->check_time->format('H:i'));
    }

    public function test_multiple_noise_clusters_throughout_day(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 08:02:00']),
            new RawLog(['check_time' => '2026-01-15 08:05:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:03:00']),
            new RawLog(['check_time' => '2026-01-15 17:00:00']),
            new RawLog(['check_time' => '2026-01-15 17:02:00']),
            new RawLog(['check_time' => '2026-01-15 17:04:00']),
        ]);

        $result = $this->reducer->reduce($logs);

        $this->assertCount(3, $result);
        $this->assertEquals('08:00', $result[0]->check_time->format('H:i'));
        $this->assertEquals('12:00', $result[1]->check_time->format('H:i'));
        $this->assertEquals('17:00', $result[2]->check_time->format('H:i'));
    }

    public function test_document_example_lunch_break_preserved(): void
    {
        $logs = collect([
            new RawLog(['check_time' => '2026-01-15 07:59:00']),
            new RawLog(['check_time' => '2026-01-15 08:00:00']),
            new RawLog(['check_time' => '2026-01-15 08:01:00']),
            new RawLog(['check_time' => '2026-01-15 12:00:00']),
            new RawLog(['check_time' => '2026-01-15 12:02:00']),
            new RawLog(['check_time' => '2026-01-15 13:01:00']),
            new RawLog(['check_time' => '2026-01-15 13:02:00']),
            new RawLog(['check_time' => '2026-01-15 17:05:00']),
        ]);

        $result = $this->reducer->reduce($logs);

        $this->assertCount(4, $result);
        $this->assertEquals('07:59', $result[0]->check_time->format('H:i'));
        $this->assertEquals('12:00', $result[1]->check_time->format('H:i'));
        $this->assertEquals('13:01', $result[2]->check_time->format('H:i'));
        $this->assertEquals('17:05', $result[3]->check_time->format('H:i'));
    }
}
