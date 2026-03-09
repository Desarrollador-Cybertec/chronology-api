<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftBreak extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftBreakFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'type',
        'start_time',
        'end_time',
        'duration_minutes',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'position' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
