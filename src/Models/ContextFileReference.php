<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

/**
 * ContextFileReference - Verknüpft ein ContextFile mit einer beliebigen Referenz
 *
 * Ermöglicht lose Kopplung: Ein ContextFile kann von mehreren Stellen referenziert werden.
 * z.B. GalleryBoard, ContentBoard-Block, Brand-Logo, Projekt-Dokument, etc.
 */
class ContextFileReference extends Model
{
    protected $table = 'context_file_references';

    protected $fillable = [
        'uuid',
        'context_file_id',
        'context_file_variant_id',
        'reference_type',
        'reference_id',
        'order',
        'meta',
    ];

    protected $casts = [
        'uuid' => 'string',
        'order' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }

            // Auto-Order: Nächste Position für diese Referenz
            if ($model->order === 0 || $model->order === null) {
                $model->order = static::where('reference_type', $model->reference_type)
                    ->where('reference_id', $model->reference_id)
                    ->max('order') + 1;
            }
        });
    }

    /**
     * Das verknüpfte ContextFile
     */
    public function contextFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class);
    }

    /**
     * Die verknüpfte Variante (optional)
     */
    public function contextFileVariant(): BelongsTo
    {
        return $this->belongsTo(ContextFileVariant::class);
    }

    /**
     * Die polymorphe Referenz (GalleryBoard, ContentBoardItem, Brand, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /**
     * Scope: Referenzen für einen bestimmten Typ + ID
     */
    public function scopeForReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)
            ->where('reference_id', $id);
    }

    /**
     * Scope: Sortiert nach Order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Helper: Meta-Wert abrufen
     */
    public function getMeta(string $key, $default = null)
    {
        return data_get($this->meta, $key, $default);
    }

    /**
     * Helper: Meta-Wert setzen
     */
    public function setMeta(string $key, $value): self
    {
        $meta = $this->meta ?? [];
        data_set($meta, $key, $value);
        $this->meta = $meta;
        return $this;
    }

    /**
     * Helper: Titel (aus Meta oder ContextFile)
     */
    public function getTitleAttribute(): string
    {
        return $this->getMeta('title') ?? $this->contextFile?->original_name ?? '';
    }

    /**
     * Helper: Beschreibung/Caption
     */
    public function getCaptionAttribute(): ?string
    {
        return $this->getMeta('caption');
    }

    /**
     * Helper: Alt-Text
     */
    public function getAltTextAttribute(): ?string
    {
        return $this->getMeta('alt_text') ?? $this->title;
    }

    /**
     * Helper: URL abrufen (Variante oder Original)
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->contextFileVariant) {
            return $this->contextFileVariant->url;
        }
        return $this->contextFile?->url;
    }

    /**
     * Helper: Thumbnail URL (für Vorschau im UI)
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->contextFileVariant) {
            return $this->contextFileVariant->url;
        }
        return $this->contextFile?->variants()
            ->where('variant_type', 'thumbnail_4_3')
            ->first()?->url
            ?? $this->contextFile?->variants()
                ->where('variant_type', 'like', 'thumbnail_%')
                ->first()?->url
            ?? $this->contextFile?->url;
    }
}
