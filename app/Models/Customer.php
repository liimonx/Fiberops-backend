<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToOrganization;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'plan',
        'status',
        'billing_status',
        'related_onu_id',
        'pppoe_username',
        'email',
        'notes',
        'location_lat',
        'location_lng',
    ];
}
