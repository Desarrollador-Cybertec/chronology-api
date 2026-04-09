<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'generated_by',
        'employee_id',
        'type',
        'date_from',
        'date_to',
        'status',
        'summary',
        'rows',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'summary' => 'array',
            'rows' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $range = $this->date_from->toDateString().' / '.$this->date_to->toDateString();

                return match ($this->type) {
                    'individual' => sprintf(
                        '%s %s',
                        ($this->relationLoaded('employee') && $this->employee
                            ? $this->employee->full_name
                            : ($this->summary['employee_name'] ?? 'Empleado')),
                        $range
                    ),
                    'tardanzas' => "Reporte de tardanzas {$range}",
                    'incompletas' => "Reporte de marcaciones incompletas {$range}",
                    'informe_total' => "Informe total de novedades {$range}",
                    'horas_laborales' => "Reporte de horas laborales {$range}",
                    default => "Reporte general {$range}",
                };
            },
        );
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
