<?php

namespace App\Models;

use App\Enums\ComplaintSource;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Complaint extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'complaint_no',
        'barangay_id',
        'resident_id',
        'respondent_id',
        'submitted_by',
        'source',
        'is_historical',
        'complaint_type_id',
        'subject',
        'description',
        'incident_date',
        'incident_time',
        'incident_location',
        'status',
        'assigned_to',
        'resolution',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => ComplaintSource::class,
            'status' => ComplaintStatus::class,
            'is_historical' => 'boolean',
            'incident_date' => 'date',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(
            Resident::class,
            'respondent_id'
        );
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function complaintType(): BelongsTo
    {
        return $this->belongsTo(
            ComplaintType::class
        );
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            ComplaintLog::class
        )->latest();
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(
            ComplaintHearing::class
        )->orderBy('hearing_date');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            ComplaintAttachment::class
        );
    }

    public function isFinal(): bool
    {
        return $this->status?->isFinal() ?? false;
    }
}
