<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        try {
            $url = Storage::disk($this->disk)->url($this->path);
            // Falls URL leer oder ungültig, verwende Route
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return route('core.context-files.show', ['token' => $this->token]);
            }
            return $url;
        } catch (\Exception $e) {
            // Fallback: Route verwenden
            return route('core.context-files.show', ['token' => $this->token]);
        }
    }

    public function getDownloadUrlAttribute(): string
    {
        $url = $this->url;
        $originalName = urlencode($this->original_name);
        // Wenn URL bereits Query-Parameter hat, verwende &, sonst ?
        $separator = str_contains($url, '?') ? '&' : '?';
        return "{$url}{$separator}download={$originalName}";
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

