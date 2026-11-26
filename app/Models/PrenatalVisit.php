<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenatalVisit extends Model
{
    protected $fillable = [
        'prenatal_record_id',
        'visit_number',
        'visit_date',
        'aog_weeks',
        'aog_days',
        'blood_pressure',
        'weight',
        'fundal_height',
        'fetal_heart_beat',
        'presentation',
        'medicine_given',
        'danger_signs',
        'hbsag',
        'hiv_screening',
        'gestational_diabetes',
        'cbc_hgb_hct',
        'rpr_rdt',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'weight' => 'decimal:2',
            'fundal_height' => 'decimal:2',
        ];
    }

    public function prenatalRecord(): BelongsTo
    {
        return $this->belongsTo(PrenatalRecord::class);
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
