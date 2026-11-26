<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

use OwenIt\Auditing\Contracts\Auditable;

class DocumentFile extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'uuid',
        'document_request_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'checksum',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function documentRequest()
    {
        return $this->belongsTo(
            DocumentRequest::class
        );
    }

    public function createdBy()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function blockchainAnchors(): HasMany
    {
        return $this->hasMany(BlockchainAnchor::class, 'document_file_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Storage Helpers
    |--------------------------------------------------------------------------
    */

    public function storageDisk()
    {
        return Storage::disk($this->disk);
    }

    public function exists(): bool
    {
        return $this->storageDisk()
            ->exists($this->path);
    }

    public function sizeInBytes(): int
    {
        return $this->size;
    }

    public function extension(): ?string
    {
        return pathinfo(
            $this->filename,
            PATHINFO_EXTENSION
        ) ?: null;
    }

    public function downloadName(): string
    {
        return $this->filename;
    }

    public function mimeType(): string
    {
        return $this->mime_type;
    }

    public function checksum(): ?string
    {
        return $this->checksum;
    }

    /*
    |--------------------------------------------------------------------------
    | File Operations
    |--------------------------------------------------------------------------
    */

    public function contents(): string
    {
        return $this->storageDisk()
            ->get($this->path);
    }

    public function deleteFile(): bool
    {
        if (! $this->exists()) {
            return true;
        }

        return $this->storageDisk()
            ->delete($this->path);
    }
}
