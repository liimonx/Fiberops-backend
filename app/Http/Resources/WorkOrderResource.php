<?php

namespace App\Http\Resources;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkOrder */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'priority' => $this->priority,
            'workType' => $this->work_type,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->assignee_id) {
            $data['assigneeId'] = $this->assignee_id;
        }
        if ($this->related_incident_id) {
            $data['relatedIncidentId'] = $this->related_incident_id;
        }
        if ($this->related_asset_id) {
            $data['relatedAssetId'] = $this->related_asset_id;
        }
        if ($this->notes) {
            $data['notes'] = $this->notes;
        }

        return $data;
    }
}
