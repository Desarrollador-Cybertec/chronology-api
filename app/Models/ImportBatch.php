<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ImportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'original_filename',
        'stored_path',
        'file_hash',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'errors',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'processed_at' => 'datetime',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rawLogs(): HasMany
    {
        return $this->hasMany(RawLog::class);
    }
}
