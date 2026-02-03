<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Collection;
use Platform\Core\Models\ContextFileReference;

/**
 * Trait für Models die ContextFileReferences nutzen wollen
 *
 * Verwendung:
 *   use HasContextFileReferences;
 *
 *   // Alle Referenzen abrufen
 *   $model->fileReferences()->get();
 *
 *   // Geordnete Referenzen
 *   $model->getOrderedFileReferences();
 *
 *   // File hinzufügen
 *   $model->addFileReference($contextFileId, ['title' => 'Mein Bild']);
 */
trait HasContextFileReferences
{
    /**
     * Alle ContextFileReferences für dieses Model
     */
    public function fileReferences()
    {
        return ContextFileReference::forReference(static::class, $this->id);
    }

    /**
     * Geordnete Referenzen mit eager-loaded Files
     */
    public function getOrderedFileReferences(): Collection
    {
        return $this->fileReferences()
            ->with(['contextFile.variants', 'contextFileVariant'])
            ->ordered()
            ->get();
    }

    /**
     * File-Referenz hinzufügen
     */
    public function addFileReference(
        int $contextFileId,
        array $meta = [],
        ?int $variantId = null
    ): ContextFileReference {
        // Prüfen ob bereits existiert (File + Variante Kombination)
        $existing = ContextFileReference::where('context_file_id', $contextFileId)
            ->where('context_file_variant_id', $variantId)
            ->where('reference_type', static::class)
            ->where('reference_id', $this->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ContextFileReference::create([
            'context_file_id' => $contextFileId,
            'context_file_variant_id' => $variantId,
            'reference_type' => static::class,
            'reference_id' => $this->id,
            'meta' => $meta,
        ]);
    }

    /**
     * Leere Referenz-Hülle erstellen (ohne File-Zuweisung)
     */
    public function addEmptyFileReference(array $meta = []): ContextFileReference
    {
        return ContextFileReference::create([
            'context_file_id' => null,
            'context_file_variant_id' => null,
            'reference_type' => static::class,
            'reference_id' => $this->id,
            'meta' => $meta,
        ]);
    }

    /**
     * File-Referenz entfernen (löscht nicht das File!)
     */
    public function removeFileReference(int $referenceId): bool
    {
        return (bool) ContextFileReference::where('id', $referenceId)
            ->where('reference_type', static::class)
            ->where('reference_id', $this->id)
            ->delete();
    }

    /**
     * Reihenfolge der Referenzen aktualisieren
     */
    public function updateFileReferenceOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            ContextFileReference::where('id', $id)
                ->where('reference_type', static::class)
                ->where('reference_id', $this->id)
                ->update(['order' => $index]);
        }
    }

    /**
     * Alle Referenzen als Array für Frontend
     */
    public function getFileReferencesArray(): array
    {
        return $this->getOrderedFileReferences()
            ->map(function ($ref) {
                return [
                    'id' => $ref->id,
                    'uuid' => $ref->uuid,
                    'title' => $ref->title,
                    'caption' => $ref->caption,
                    'alt_text' => $ref->alt_text,
                    'order' => $ref->order,
                    'context_file_id' => $ref->context_file_id,
                    'context_file_variant_id' => $ref->context_file_variant_id,
                    'variant_type' => $ref->contextFileVariant?->variant_type,
                    'thumbnail' => $ref->thumbnail_url,
                    'url' => $ref->url,
                    'meta' => $ref->meta,
                ];
            })
            ->toArray();
    }
}
