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

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getDownloadUrlAttribute(): string
    {
        $url = Storage::disk($this->disk)->url($this->path);
        $originalName = urlencode($this->original_name);
        return "{$url}?download={$originalName}";
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getThumbnailAttribute()
    {
        return $this->variants()->where('variant_type', 'thumbnail')->first();
    }
}

