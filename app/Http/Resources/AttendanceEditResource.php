<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceEditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_day_id' => $this->attendance_day_id,
            'edited_by' => $this->edited_by,
            'editor' => $this->whenLoaded('editedBy', fn () => [
                'id' => $this->editedBy->id,
                'name' => $this->editedBy->name,
            ]),
            'field_changed' => $this->field_changed,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
