<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrenatalRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'resident_id',
        'barangay_id',
        'registration_date',
        'spouse_name',
        'ob_score',
        'lmp',
        'edc',
        'height',
        'initial_weight',
        'bmi',
        'past_medical_history',
        'family_history',
        'tt1_date',
        'tt2_date',
        'tt3_date',
        'tt4_date',
        'tt5_date',
        'pregnancy_outcome',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'lmp' => 'date',
            'edc' => 'date',
            'tt1_date' => 'date',
            'tt2_date' => 'date',
            'tt3_date' => 'date',
            'tt4_date' => 'date',
            'tt5_date' => 'date',
            'height' => 'decimal:2',
            'initial_weight' => 'decimal:2',
            'bmi' => 'decimal:2',
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

    public function visits(): HasMany
    {
        return $this->hasMany(PrenatalVisit::class);
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
