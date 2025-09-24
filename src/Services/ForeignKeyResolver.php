<?php

namespace Platform\Core\Services;

use Platform\Core\Schema\ModelSchemaRegistry as Schemas;

class ForeignKeyResolver
{
    /**
     * Koerziere Fremdschlüssel in $data anhand des ModelSchemas:
     * - Wenn Feld ein belongsTo ist und als String übergeben wird, suche Zielmodell über label_key
     * - Bei eindeutigem Treffer → ersetze mit ID
     * - Bei Mehrfachtreffer/kein Treffer → gebe needResolve-Info zurück
     */
    public function coerce(string $modelKey, array $data): array
    {
        $fkMap = Schemas::foreignKeys($modelKey);
        $needResolve = null;
        foreach ($fkMap as $fkField => $fkMeta) {
            if (!array_key_exists($fkField, $data) || $data[$fkField] === null || $data[$fkField] === '') {
                continue;
            }
            // numerisch? direkt übernehmen
            if (is_int($data[$fkField]) || (is_string($data[$fkField]) && ctype_digit((string) $data[$fkField]))) {
                $data[$fkField] = (int) $data[$fkField];
                continue;
            }
            $targetModelKey = $fkMeta['references'] ?? ($fkMeta['target'] ?? null);
            if (!$targetModelKey) continue;
            $labelKey = $fkMeta['label_key'] ?? Schemas::meta($targetModelKey, 'label_key') ?? 'name';
            $targetClass = Schemas::meta($targetModelKey, 'eloquent');
            if (!$targetClass || !class_exists($targetClass)) continue;
            $term = trim((string) $data[$fkField]);
            if ($term === '') continue;
            try {
                $matches = $targetClass::where($labelKey, 'LIKE', '%'.$term.'%')
                    ->orderByRaw('CASE WHEN '.$labelKey.' = ? THEN 0 ELSE 1 END', [$term])
                    ->orderBy($labelKey)
                    ->limit(5)
                    ->get(['id', $labelKey]);
            } catch (\Throwable $e) {
                continue;
            }
            if ($matches->count() === 1) {
                $data[$fkField] = $matches->first()->id;
            } elseif ($matches->count() > 1) {
                $choices = $matches->map(function($m) use ($labelKey){
                    return ['id' => $m->id, 'label' => $m->{$labelKey} ?? (string)$m->id];
                })->toArray();
                $needResolve = [
                    'field' => $fkField,
                    'message' => 'Bitte wählen: '.$fkField,
                    'choices' => $choices,
                ];
                break;
            } else {
                $needResolve = [
                    'field' => $fkField,
                    'message' => 'Referenz nicht gefunden: '.$fkField,
                ];
                break;
            }
        }
        return [
            'data' => $data,
            'needResolve' => $needResolve,
        ];
    }
}


