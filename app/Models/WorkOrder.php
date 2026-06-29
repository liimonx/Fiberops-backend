<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use BelongsToOrganization;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'title',
        'priority',
        'work_type',
        'status',
        'assignee_id',
        'related_incident_id',
        'related_asset_id',
        'notes',
    ];
}
