<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = [

        'city_id',

        'psgc_code',

        'barangay',

        'is_active',

    ];

    protected $casts = [

        'is_active' => 'boolean',

    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function officials(): HasMany
    {
        return $this->hasMany(
            BarangayOfficial::class
        );
    }
}
