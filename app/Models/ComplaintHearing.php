<?php

namespace App\Models;

use App\Enums\ComplaintHearingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintHearing extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'hearing_date',
        'hearing_time',
        'location',
        'status',
        'remarks',
        'scheduled_by',
    ];

    protected function casts(): array
    {
        return [
            'hearing_date' => 'date',
            'status' => ComplaintHearingStatus::class,
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(
            Complaint::class
        );
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'scheduled_by'
        );
    }
}
