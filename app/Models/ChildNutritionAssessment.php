<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildNutritionAssessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'child_nutrition_monitoring_id',
        'assessment_month',
        'assessment_date',
        'age_months',
        'weight',
        'height',
        'muac',
        'edema',
        'weight_for_age',
        'height_for_age',
        'weight_for_height',
        'muac_edema_status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'assessment_month' => 'integer',
            'assessment_date' => 'date',
            'age_months' => 'integer',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'muac' => 'decimal:2',
        ];
    }

    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(
            ChildNutritionMonitoring::class,
            'child_nutrition_monitoring_id'
        );
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
