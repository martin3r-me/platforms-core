<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Platform\Core\Traits\HasTags;

class Document extends Model
{
    use HasTags;
    protected $fillable = [
        'team_id',
        'document_folder_id',
        'document_template_id',
        'template_key',
        'title',
        'data',
        'status',
        'output_context_file_id',
        'meta',
        'created_by_user_id',
        'share_token',
    ];

    protected $casts = [
        'data' => 'array',
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'document_folder_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function outputFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class, 'output_context_file_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(DocumentExport::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getOutputUrlAttribute(): ?string
    {
        return $this->outputFile?->url;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->outputFile?->download_url;
    }

    /**
     * Public share URL (no auth required).
     */
    public function getShareUrlAttribute(): ?string
    {
        if (!$this->share_token) {
            return null;
        }

        return route('core.documents.share', ['token' => $this->share_token]);
    }

    /**
     * Generate a share token if not already set.
     */
    public function ensureShareToken(): string
    {
        if (!$this->share_token) {
            $this->update(['share_token' => Str::random(48)]);
        }

        return $this->share_token;
    }
}
