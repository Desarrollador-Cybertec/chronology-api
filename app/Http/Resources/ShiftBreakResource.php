<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftBreakResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
