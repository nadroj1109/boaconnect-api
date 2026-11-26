<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [

        'region_id',

        'province_id',

        'psgc_code',

        'city',

        'zip_code',

        'is_city',

    ];

    protected $casts = [

        'is_city' => 'boolean',

    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function barangays()
    {
        return $this->hasMany(Barangay::class);
    }
}
