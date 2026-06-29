<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'plan' => $this->plan,
            'status' => $this->status,
            'billingStatus' => $this->billing_status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->related_onu_id) {
            $data['relatedOnuId'] = $this->related_onu_id;
        }
        if ($this->pppoe_username) {
            $data['pppoeUsername'] = $this->pppoe_username;
        }
        if ($this->email) {
            $data['email'] = $this->email;
        }
        if ($this->notes) {
            $data['notes'] = $this->notes;
        }
        if ($this->location_lat !== null && $this->location_lng !== null) {
            $data['location'] = [
                'lat' => (float) $this->location_lat,
                'lng' => (float) $this->location_lng,
            ];
        }

        return $data;
    }
}
