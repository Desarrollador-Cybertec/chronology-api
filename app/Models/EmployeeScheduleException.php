<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeScheduleException extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeScheduleExceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'shift_id',
        'is_working_day',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_working_day' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
