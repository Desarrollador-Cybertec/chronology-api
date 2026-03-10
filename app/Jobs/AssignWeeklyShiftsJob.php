<?php

namespace App\Jobs;

use App\Models\EmployeeShiftAssignment;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\Shift;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class AssignWeeklyShiftsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ImportBatch $batch) {}

    /**
     * Analyze raw_logs from this batch, group by employee and ISO week,
     * detect shift patterns per week, and create EmployeeShiftAssignment records.
     */
    public function handle(): void
    {
        $autoAssign = SystemSetting::getValue('auto_assign_shift', 'true') === 'true';

        if (! $autoAssign) {
            return;
        }

        $toleranceMinutes = (int) SystemSetting::getValue('auto_assign_tolerance_minutes', '30');
        $regularityPercent = (int) SystemSetting::getValue('auto_assign_regularity_percent', '70');

        $shifts = Shift::query()->with('breaks')->where('is_active', true)->get();

        if ($shifts->isEmpty()) {
            return;
        }

        $employeeWeeks = $this->buildEmployeeWeekMap();

        foreach ($employeeWeeks as $employeeId => $weeks) {
            foreach ($weeks as $weekKey => $firstCheckIns) {
                $this->processWeek($employeeId, $weekKey, $firstCheckIns, $shifts, $toleranceMinutes, $regularityPercent);
            }
        }
    }

    /**
     * Build a map: employeeId → [weekKey → [firstCheckIn Carbon, ...]]
     *
     * @return array<int, array<string, Collection<int, Carbon>>>
     */
    private function buildEmployeeWeekMap(): array
    {
        $rows = RawLog::query()
            ->where('import_batch_id', $this->batch->id)
            ->selectRaw('employee_id, date_reference, MIN(check_time) as first_check_in')
            ->groupBy('employee_id', 'date_reference')
            ->orderBy('employee_id')
            ->orderBy('date_reference')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $date = Carbon::parse($row->date_reference);
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekKey = $weekStart->format('Y-W');

            $map[$row->employee_id] ??= [];
            $map[$row->employee_id][$weekKey] ??= collect();
            $map[$row->employee_id][$weekKey]->push(Carbon::parse($row->first_check_in));
        }

        return $map;
    }

    /**
     * For a given employee+week, detect the best matching shift and create an assignment.
     *
     * @param  Collection<int, Carbon>  $firstCheckIns
     * @param  Collection<int, Shift>  $shifts
     */
    private function processWeek(
        int $employeeId,
        string $weekKey,
        Collection $firstCheckIns,
        Collection $shifts,
        int $toleranceMinutes,
        int $regularityPercent,
    ): void {
        [$year, $week] = explode('-', $weekKey);
        $weekStart = CarbonImmutable::now()
            ->setISODate((int) $year, (int) $week)
            ->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->endOfWeek(Carbon::FRIDAY);

        // Skip if the employee already has an assignment covering this week
        $existingAssignment = EmployeeShiftAssignment::query()
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $weekEnd->toDateString())
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $weekStart->toDateString());
            })
            ->exists();

        if ($existingAssignment) {
            return;
        }

        $bestShift = null;
        $bestPercent = 0;

        foreach ($shifts as $shift) {
            $matchCount = 0;

            foreach ($firstCheckIns as $checkIn) {
                $shiftStart = $checkIn->copy()->setTimeFromTimeString($shift->start_time);
                $windowStart = $shiftStart->copy()->subMinutes($toleranceMinutes);
                $windowEnd = $shiftStart->copy()->addMinutes($toleranceMinutes);

                if ($checkIn->between($windowStart, $windowEnd)) {
                    $matchCount++;
                }
            }

            $percent = ($matchCount / $firstCheckIns->count()) * 100;

            if ($percent >= $regularityPercent && $percent > $bestPercent) {
                $bestPercent = $percent;
                $bestShift = $shift;
            }
        }

        if ($bestShift) {
            // Detect which days of the week the employee actually worked
            $workDays = $firstCheckIns->map(fn (Carbon $c): int => $c->dayOfWeek)->unique()->sort()->values()->all();

            EmployeeShiftAssignment::create([
                'employee_id' => $employeeId,
                'shift_id' => $bestShift->id,
                'effective_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'work_days' => $workDays,
            ]);
        }
    }
}
