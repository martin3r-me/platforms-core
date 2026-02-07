<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;

trait HasExtraFields
{
    /**
     * Alle Extra Field Werte dieser Entity
     */
    public function extraFieldValues(): MorphMany
    {
        return $this->morphMany(CoreExtraFieldValue::class, 'fieldable');
    }

    /**
     * Lädt alle verfügbaren Extra Field Definitionen für diese Entity
     *
     * Phase 1:
     * 1. Definitionen für dieses konkrete Objekt (context_id = $this->id)
     * 2. + Definitionen für den Typ (context_id = null) → gilt für ALLE dieses Typs
     *
     * Phase 2 (später):
     * 3. + Geerbte Definitionen von Parent (via Inheritance-Mapping)
     */
    public function getExtraFieldDefinitions(): Collection
    {
        $teamId = $this->getTeamIdForExtraFields();
        if (!$teamId) {
            return collect();
        }

        return CoreExtraFieldDefinition::query()
            ->forTeam($teamId)
            ->forContext(get_class($this), $this->id)
            ->orderBy('order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Gibt den Wert eines Extra Fields zurück
     */
    public function getExtraField(string $name): mixed
    {
        $definition = $this->findExtraFieldDefinition($name);
        if (!$definition) {
            return null;
        }

        $value = $this->extraFieldValues()
            ->where('definition_id', $definition->id)
            ->first();

        return $value?->typed_value;
    }

    /**
     * Setzt den Wert eines Extra Fields
     */
    public function setExtraField(string $name, mixed $value): void
    {
        $definition = $this->findExtraFieldDefinition($name);
        if (!$definition) {
            return;
        }

        $fieldValue = $this->extraFieldValues()
            ->where('definition_id', $definition->id)
            ->first();

        if ($value === null || $value === '') {
            // Wert löschen wenn leer
            if ($fieldValue) {
                $fieldValue->delete();
            }
            return;
        }

        if (!$fieldValue) {
            $fieldValue = new CoreExtraFieldValue([
                'definition_id' => $definition->id,
                'fieldable_type' => get_class($this),
                'fieldable_id' => $this->id,
            ]);
        }

        $fieldValue->setTypedValue($value);
        $fieldValue->save();
    }

    /**
     * Gibt alle Extra Fields als Array zurück
     * Format: ['field_name' => value, ...]
     */
    public function getExtraFieldsArray(): array
    {
        $definitions = $this->getExtraFieldDefinitions();
        $values = $this->extraFieldValues()->with('definition')->get()->keyBy('definition_id');

        $result = [];
        foreach ($definitions as $definition) {
            $value = $values->get($definition->id);
            $result[$definition->name] = $value?->typed_value;
        }

        return $result;
    }

    /**
     * Gibt alle Extra Fields mit Labels zurück
     * Format: [['name' => 'field_name', 'label' => 'Field Label', 'value' => value, 'type' => 'text'], ...]
     */
    public function getExtraFieldsWithLabels(): array
    {
        $definitions = $this->getExtraFieldDefinitions();
        $values = $this->extraFieldValues()->with('definition')->get()->keyBy('definition_id');

        $result = [];
        foreach ($definitions as $definition) {
            $value = $values->get($definition->id);
            $result[] = [
                'id' => $definition->id,
                'name' => $definition->name,
                'label' => $definition->label,
                'type' => $definition->type,
                'value' => $value?->typed_value,
                'is_required' => $definition->is_required,
                'is_encrypted' => $definition->is_encrypted,
                'options' => $definition->options,
            ];
        }

        return $result;
    }

    /**
     * Sucht eine Extra Field Definition anhand des Namens
     */
    protected function findExtraFieldDefinition(string $name): ?CoreExtraFieldDefinition
    {
        $teamId = $this->getTeamIdForExtraFields();
        if (!$teamId) {
            return null;
        }

        return CoreExtraFieldDefinition::query()
            ->forTeam($teamId)
            ->forContext(get_class($this), $this->id)
            ->where('name', $name)
            ->first();
    }

    /**
     * Ermittelt die Team-ID für Extra Fields
     */
    protected function getTeamIdForExtraFields(): ?int
    {
        // Wenn Entity selbst team_id hat, verwende diese
        if (isset($this->team_id) && $this->team_id) {
            return $this->team_id;
        }

        // Fallback: Aktuelles Team des Users
        try {
            $user = Auth::user();
            if (!$user) {
                return null;
            }

            $baseTeam = $user->currentTeamRelation;
            return $baseTeam?->id;
        } catch (\Exception $e) {
            return null;
        }
    }
}
