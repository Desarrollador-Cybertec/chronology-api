<?php

namespace App\Domain\Attendance;

use App\Models\Shift;

class ScheduleResult
{
    public function __construct(
        public bool $isWorkingDay,
        public ?Shift $shift,
        public string $source,
    ) {}
}
