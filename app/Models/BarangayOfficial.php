<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class BarangayOfficial extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use AuditableTrait;

    protected $auditInclude = [
        'barangay_id',
        'resident_id',
        'official_position_id',
        'term_start',
        'term_end',
        'is_active',
    ];

    protected $fillable = [
        'barangay_id',
        'resident_id',
        'official_position_id',
        'term_start',
        'term_end',
        'is_active',
    ];

    protected $casts = [
        'term_start' => 'date',
        'term_end' => 'date',
        'is_active' => 'boolean',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(
            Barangay::class
        );
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(
            Resident::class
        );
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(
            OfficialPosition::class,
            'official_position_id'
        );
    }
}
