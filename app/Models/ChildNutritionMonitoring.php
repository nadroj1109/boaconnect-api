<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChildNutritionMonitoring extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'resident_id',
        'barangay_id',
        'monitoring_year',
        'sequence_no',
        'caregiver_name',
        'birthweight',
        'low_birth_weight',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monitoring_year' => 'integer',
            'sequence_no' => 'integer',
            'birthweight' => 'decimal:2',
            'low_birth_weight' => 'boolean',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ChildNutritionAssessment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
