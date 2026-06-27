<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'sla_policies';

    protected $fillable = [
        'organization_id',
        'priority',
        'response_minutes',
        'resolution_minutes',
    ];
}
