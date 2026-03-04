<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEdit extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceEditFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_day_id',
        'edited_by',
        'field_changed',
        'old_value',
        'new_value',
        'reason',
    ];

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
