<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostelRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'hostel_id' => $this->hostel_id,
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            'occupied' => $this->activeOccupantCount(),
            'is_active' => $this->is_active,
        ];
    }
}
