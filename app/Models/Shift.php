<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'crosses_midnight',
        'lunch_required',
        'lunch_duration_minutes',
        'tolerance_minutes',
        'overtime_enabled',
        'overtime_min_block_minutes',
        'max_daily_overtime_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'lunch_required' => 'boolean',
            'overtime_enabled' => 'boolean',
            'is_active' => 'boolean',
            'lunch_duration_minutes' => 'integer',
            'tolerance_minutes' => 'integer',
            'overtime_min_block_minutes' => 'integer',
            'max_daily_overtime_minutes' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }
}
