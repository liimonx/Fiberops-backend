<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->name,
            'status' => $this->status,
            'location' => [
                'lat' => (float) $this->location_lat,
                'lng' => (float) $this->location_lng,
            ],
            ...($this->monitor_host ? ['monitorHost' => $this->monitor_host] : []),
        ];
    }
}
