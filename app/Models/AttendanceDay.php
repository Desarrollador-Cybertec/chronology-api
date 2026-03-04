<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDay extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceDayFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date_reference',
        'shift_id',
        'first_check_in',
        'last_check_out',
        'worked_minutes',
        'overtime_minutes',
        'overtime_diurnal_minutes',
        'overtime_nocturnal_minutes',
        'late_minutes',
        'early_departure_minutes',
        'status',
        'is_manually_edited',
    ];

    protected function casts(): array
    {
        return [
            'date_reference' => 'date',
            'first_check_in' => 'datetime',
            'last_check_out' => 'datetime',
            'worked_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'overtime_diurnal_minutes' => 'integer',
            'overtime_nocturnal_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_departure_minutes' => 'integer',
            'is_manually_edited' => 'boolean',
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

    public function edits(): HasMany
    {
        return $this->hasMany(AttendanceEdit::class);
    }
}
