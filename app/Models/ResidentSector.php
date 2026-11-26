<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentSector extends Model
{
    protected $fillable = [

        'resident_id',

        'sector_id',

        'registered',

    ];

    protected $casts = [

        'registered' => 'boolean',

    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
