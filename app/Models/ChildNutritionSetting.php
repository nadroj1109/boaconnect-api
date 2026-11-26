<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildNutritionSetting extends Model
{
    protected $fillable = [
        'age_restriction_enabled',
        'minimum_age_months',
        'maximum_age_months',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'age_restriction_enabled' => 'boolean',
            'minimum_age_months' => 'integer',
            'maximum_age_months' => 'integer',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
