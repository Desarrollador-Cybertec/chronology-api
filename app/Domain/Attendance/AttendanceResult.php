<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;

class AttendanceResult
{
    public function __construct(
        public ?Carbon $firstCheck = null,
        public ?Carbon $lastCheck = null,
        public int $workedMinutes = 0,
        public int $lateMinutes = 0,
        public int $earlyDepartureMinutes = 0,
        public int $overtimeMinutes = 0,
        public int $overtimeDiurnalMinutes = 0,
        public int $overtimeNocturnalMinutes = 0,
        public string $status = 'absent',
        public ?Shift $shift = null,
    ) {}

    public static function rest(): self
    {
        return new self(status: 'rest');
    }
}
