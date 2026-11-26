<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ethnicity extends Model
{
    protected $fillable = ['ethnicity', 'is_active'];

    protected $casts = [

        'is_active' => 'boolean',

    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class, 'ethnicity_id');
    }
}
