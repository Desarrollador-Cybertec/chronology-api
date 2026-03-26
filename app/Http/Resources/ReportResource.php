<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'generated_by' => $this->generated_by,
            'employee_code' => $this->whenLoaded('employee', fn () => $this->employee?->internal_id),
            'type' => $this->type,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'status' => $this->status,
            'summary' => $this->when($this->status === 'completed', $this->summary),
            'rows' => $this->when(
                $this->status === 'completed' && $request->boolean('include_rows', true),
                $this->rows,
            ),
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'generated_by_user' => $this->whenLoaded('generatedBy', fn () => [
                'id' => $this->generatedBy->id,
                'name' => $this->generatedBy->name,
            ]),
        ];
    }
}
