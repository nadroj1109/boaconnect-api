<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = [

        'sector_code',

        'sector_name',

        'is_registrable',

        'sort_order',

        'is_active',

    ];

    protected $casts = [

        'is_registrable' => 'boolean',

        'is_active' => 'boolean',

    ];

    public function residentSectors()
    {
        return $this->hasMany(ResidentSector::class);
    }
}
