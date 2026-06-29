<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'organization',
        'integrations',
        'billing',
        'team',
    ];

    protected function casts(): array
    {
        return [
            'organization' => 'array',
            'integrations' => 'array',
            'billing' => 'array',
            'team' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
