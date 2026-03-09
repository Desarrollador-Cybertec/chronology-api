<?php

namespace App\Domain\Attendance;

use App\Models\EmployeeShiftAssignment;
use App\Models\RawLog;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AutoShiftAssigner
{
    /**
     * Try to resolve a shift for an employee based on regularity of their check-in times.
     *
     * Analyzes historical first check-ins from raw_logs to determine if the employee
     * has a consistent pattern matching a shift's start_time ± tolerance window.
     * Only assigns a shift when enough days of data exist and the regularity threshold is met.
     *
     * @param  int  $employeeId  The employee being processed
     * @param  Carbon  $dateReference  The date of the attendance record
     * @param  Carbon  $firstCheckIn  The employee's first punch of the day
     * @param  int  $toleranceMinutes  Window around shift start_time (default 30)
     * @param  int  $minDays  Minimum days of data required before attempting assignment
     * @param  int  $regularityPercent  Percentage of days that must match a single shift
     */
    public function resolve(
        int $employeeId,
        Carbon $dateReference,
        Carbon $firstCheckIn,
        int $toleranceMinutes = 30,
        int $minDays = 3,
        int $regularityPercent = 70,
    ): ?Shift {
        // Skip if the employee already has an active shift assignment covering this date
        $existingAssignment = EmployeeShiftAssignment::query()
            ->with('shift.breaks')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $dateReference->toDateString())
            ->where(function ($q) use ($dateReference) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $dateReference->toDateString());
            })
            ->first();

        if ($existingAssignment) {
            return $existingAssignment->shift;
        }

        // Gather historical first check-ins (including current day)
        $historicalCheckIns = $this->getHistoricalFirstCheckIns($employeeId, $dateReference);
        $allCheckIns = $historicalCheckIns->push($firstCheckIn);

        // Need minimum number of days before attempting assignment
        if ($allCheckIns->count() < $minDays) {
            return null;
        }

        $shifts = Shift::query()->with('breaks')->where('is_active', true)->get();

        if ($shifts->isEmpty()) {
            return null;
        }

        // Evaluate regularity for each shift
        $bestShift = null;
        $bestPercent = 0;

        foreach ($shifts as $shift) {
            $matchCount = 0;

            foreach ($allCheckIns as $checkIn) {
                $shiftStart = $checkIn->copy()->setTimeFromTimeString($shift->start_time);
                $windowStart = $shiftStart->copy()->subMinutes($toleranceMinutes);
                $windowEnd = $shiftStart->copy()->addMinutes($toleranceMinutes);

                if ($checkIn->between($windowStart, $windowEnd)) {
                    $matchCount++;
                }
            }

            $percent = ($matchCount / $allCheckIns->count()) * 100;

            if ($percent >= $regularityPercent && $percent > $bestPercent) {
                $bestPercent = $percent;
                $bestShift = $shift;
            }
        }

        if ($bestShift) {
            $earliestDate = RawLog::query()
                ->where('employee_id', $employeeId)
                ->orderBy('date_reference')
                ->value('date_reference') ?? $dateReference->toDateString();

            EmployeeShiftAssignment::create([
                'employee_id' => $employeeId,
                'shift_id' => $bestShift->id,
                'effective_date' => $earliestDate,
                'work_days' => [1, 2, 3, 4, 5],
            ]);
        }

        return $bestShift;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function getHistoricalFirstCheckIns(int $employeeId, Carbon $dateReference): Collection
    {
        return RawLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date_reference', '<', $dateReference->toDateString())
            ->selectRaw('date_reference, MIN(check_time) as first_check_in')
            ->groupBy('date_reference')
            ->orderBy('date_reference')
            ->get()
            ->pluck('first_check_in')
            ->map(fn (string $t): Carbon => Carbon::parse($t));
    }
}
