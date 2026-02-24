<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Services\ContextFileService;

class ContextFile extends Model
{
    protected $fillable = [
        'token',
        'team_id',
        'user_id',
        'context_type',
        'context_id',
        'disk',
        'path',
        'file_name',
        'original_name',
        'mime_type',
        'file_size',
        'width',
        'height',
        'keep_original',
        'meta',
        'variants_status',
    ];

    protected $casts = [
        'meta' => 'array',
        'keep_original' => 'boolean',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ContextFileVariant::class);
    }

    /**
     * Referenzen zu diesem File (wo wird es verwendet?)
     */
    public function references(): HasMany
    {
        return $this->hasMany(ContextFileReference::class);
    }

    public function getUrlAttribute(): string
    {
        return ContextFileService::generateUrl($this->disk, $this->path, $this->token, 'core.context-files.show', 60);
    }

    public function getDownloadUrlAttribute(): string
    {
        return ContextFileService::generateDownloadUrl($this->disk, $this->path, $this->token, $this->original_name, 5);
    }

    /**
     * URL for external services (e.g. OpenAI Vision) with shorter TTL.
     */
    public function getUrlForExternalService(int $ttl = 15): string
    {
        return ContextFileService::generateUrl($this->disk, $this->path, $this->token, 'core.context-files.show', $ttl);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getThumbnailAttribute()
    {
        // Suche nach thumbnail_4_3 (Standard), sonst erstes verfügbares Thumbnail
        return $this->variants()->where('variant_type', 'thumbnail_4_3')->first()
            ?? $this->variants()->where('variant_type', 'like', 'thumbnail_%')->first();
    }
}

