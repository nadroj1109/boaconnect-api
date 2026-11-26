<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentHouseholdRegistration extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'resident_id',
        'requested_household_id',
        'household_details',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'household_details' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function requestedHousehold()
    {
        return $this->belongsTo(Household::class, 'requested_household_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
