<?php

namespace App\Http\Resources;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Incident */
class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'severity' => $this->severity,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->related_asset_id) {
            $data['relatedAssetId'] = $this->related_asset_id;
        }
        if ($this->technician) {
            $data['technician'] = $this->technician;
        }
        if ($this->notes) {
            $data['notes'] = $this->notes;
        }
        if ($this->resolution_notes) {
            $data['resolutionNotes'] = $this->resolution_notes;
        }
        if ($this->resolved_at) {
            $data['resolvedAt'] = $this->resolved_at->toIso8601String();
        }

        return $data;
    }
}
