<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class GeneratedReport extends Model
{
    use BelongsToOrganization;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'type',
        'format',
        'title',
        'status',
        'period',
        'generated_at',
        'generated_by',
        'file_size_bytes',
        'download_payload',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'download_payload' => 'array',
        ];
    }
}
