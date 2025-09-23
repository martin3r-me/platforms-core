<?php

namespace Platform\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Schema\ModelSchemaRegistry as Schemas;

class GenericModelCommandService
{
    public function query(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        if ($modelKey === '') {
            return ['ok' => false, 'message' => 'Modell wählen', 'needResolve' => true, 'choices' => Schemas::keys()];
        }
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) return ['ok' => false, 'message' => 'Unbekanntes Modell'];

        $q          = trim((string)($slots['q'] ?? ''));
        $sort       = Schemas::validateSort($modelKey, $slots['sort'] ?? null, 'id');
        $order      = strtolower((string)($slots['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $limit      = min(max((int)($slots['limit'] ?? 20), 1), 100);
        $fieldsReq  = array_map('trim', explode(',', (string)($slots['fields'] ?? '')));
        if (empty($fieldsReq) || $fieldsReq === ['']) {
            $fieldsReq = array_slice(Schemas::get($modelKey)['selectable'] ?? [], 0, 6);
        }
        $fields     = Schemas::validateFields($modelKey, $fieldsReq, ['id']);

        /** @var Builder $query */
        $query = $eloquent::query();
        if (Schema::hasColumn((new $eloquent)->getTable(), 'team_id') && auth()->check()) {
            $query->where('team_id', auth()->user()->currentTeam?->id);
        }
        if ($q !== '') {
            $schemaFields = Schemas::get($modelKey)['fields'] ?? [];
            foreach (['title','name'] as $candidate) {
                if (in_array($candidate, $schemaFields, true)) {
                    $query->where($candidate, 'LIKE', '%'.$q.'%');
                    break;
                }
            }
        }
        $filters = Schemas::validateFilters($modelKey, (array)($slots['filters'] ?? []));
        foreach ($filters as $k => $v) {
            if ($v === null || $v === '') continue;
            $query->where($k, $v);
        }
        $rows = $query->orderBy($sort, $order)->limit($limit)->get($fields);
        return ['ok' => true, 'data' => ['items' => $rows->toArray()], 'message' => 'Gefunden ('.$rows->count().')'];
    }

    public function open(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        $route    = Schemas::meta($modelKey, 'show_route');
        $param    = Schemas::meta($modelKey, 'route_param');
        if (!$eloquent) return ['ok' => false, 'message' => 'Unbekanntes Modell'];
        $id = $slots['id'] ?? null;
        $uuid = $slots['uuid'] ?? null;
        $name = $slots['name'] ?? null;
        $row = null;
        if ($id) {
            $row = $eloquent::find($id);
        } elseif ($uuid && in_array('uuid', Schemas::get($modelKey)['fields'] ?? [], true)) {
            $row = $eloquent::where('uuid', $uuid)->first();
        } elseif ($name) {
            $titleField = in_array('title', Schemas::get($modelKey)['fields'] ?? [], true) ? 'title' : (in_array('name', Schemas::get($modelKey)['fields'] ?? [], true) ? 'name' : null);
            if ($titleField) {
                $matches = $eloquent::where($titleField, 'LIKE', '%'.$name.'%')
                    ->orderByRaw('CASE WHEN '.$titleField.' = ? THEN 0 ELSE 1 END', [$name])
                    ->orderBy($titleField)
                    ->limit(5)
                    ->get(['id', $titleField]);
                if ($matches->count() === 1) {
                    $row = $matches->first();
                } elseif ($matches->count() > 1) {
                    $labelKey = Schemas::meta($modelKey, 'label_key') ?: $titleField;
                    $choices = $matches->map(function($m) use ($labelKey){
                        return ['id' => $m->id, 'label' => $m->{$labelKey} ?? (string)$m->id];
                    })->toArray();
                    return ['ok' => false, 'message' => 'Bitte wählen', 'needResolve' => true, 'choices' => $choices];
                }
            }
        }
        if (!$row) return ['ok' => false, 'message' => 'Eintrag nicht gefunden', 'needResolve' => true];
        if ($route && $param) {
            return ['ok' => true, 'navigate' => route($route, [$param => $row->id]), 'message' => 'Navigation bereit'];
        }
        return ['ok' => true, 'data' => ['id' => $row->id], 'message' => 'Gefunden'];
    }

    public function create(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        $data = (array)($slots['data'] ?? []);
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) return ['ok' => false, 'message' => 'Unbekanntes Modell'];
        $required = Schemas::required($modelKey);
        $writable = Schemas::writable($modelKey);
        // Sanitize einfache Textfelder (insbesondere title/name)
        if (isset($data['title'])) {
            $data['title'] = $this->sanitizeTitle((string) $data['title']);
        }
        if (isset($data['name'])) {
            $data['name'] = $this->sanitizeTitle((string) $data['name']);
        }
        // Fremdschlüssel (belongsTo) per Label auflösen, falls String übergeben
        $fkMap = Schemas::foreignKeys($modelKey);
        foreach ($fkMap as $fkField => $fkMeta) {
            if (!array_key_exists($fkField, $data) || $data[$fkField] === null || $data[$fkField] === '') continue;
            // Bereits numerisch? Dann übernehmen
            if (is_int($data[$fkField]) || (is_string($data[$fkField]) && ctype_digit((string) $data[$fkField]))) {
                $data[$fkField] = (int) $data[$fkField];
                continue;
            }
            $targetModelKey = $fkMeta['references'] ?? ($fkMeta['target'] ?? null);
            if (!$targetModelKey) continue;
            $labelKey = $fkMeta['label_key'] ?? Schemas::meta($targetModelKey, 'label_key') ?? 'name';
            $targetClass = Schemas::meta($targetModelKey, 'eloquent');
            if (!$targetClass || !class_exists($targetClass)) continue;
            $term = (string) $data[$fkField];
            $matches = $targetClass::where($labelKey, 'LIKE', '%'.$term.'%')
                ->orderByRaw('CASE WHEN '.$labelKey.' = ? THEN 0 ELSE 1 END', [$term])
                ->orderBy($labelKey)
                ->limit(5)
                ->get(['id', $labelKey]);
            if ($matches->count() === 1) {
                $data[$fkField] = $matches->first()->id;
            } elseif ($matches->count() > 1) {
                $choices = $matches->map(function($m) use ($labelKey){
                    return ['id' => $m->id, 'label' => $m->{$labelKey} ?? (string) $m->id];
                })->toArray();
                return [
                    'ok' => false,
                    'message' => 'Bitte wählen: '.$fkField,
                    'needResolve' => true,
                    'choices' => $choices,
                ];
            } else {
                return [
                    'ok' => false,
                    'message' => 'Referenz nicht gefunden: '.$fkField,
                    'needResolve' => true,
                ];
            }
        }
        foreach ($required as $f) {
            if (!array_key_exists($f, $data) || $data[$f] === null || $data[$f] === '') {
                return ['ok' => false, 'message' => 'Pflichtfeld fehlt: '.$f, 'needResolve' => true, 'missing' => $required];
            }
        }
        $payload = [];
        foreach ($writable as $f) {
            if (array_key_exists($f, $data)) {
                $payload[$f] = $data[$f];
            }
        }
        if (Schema::hasColumn((new $eloquent)->getTable(), 'team_id') && auth()->check()) {
            $payload['team_id'] = auth()->user()->currentTeam?->id;
        }
        $row = new $eloquent();
        $row->fill($payload);
        $row->save();
        $route = Schemas::meta($modelKey, 'show_route');
        $param = Schemas::meta($modelKey, 'route_param');
        $navigate = ($route && $param) ? route($route, [$param => $row->id]) : null;
        return ['ok' => true, 'message' => 'Angelegt', 'data' => ['id' => $row->id], 'navigate' => $navigate];
    }

    protected function sanitizeTitle(string $title): string
    {
        // Entferne Anführungszeichen, führende Aktionswörter und Füllwörter
        $t = trim($title);
        $t = trim($t, "\"'` “”‚‘");
        $t = preg_replace('/\b(erstelle|erstellen|anlegen|bitte|danke|ok|okay|mach|machen|lege|create)\b/i', '', $t);
        $t = preg_replace('/\s{2,}/', ' ', $t ?? '');
        $t = trim($t, ' .,-:_');
        if (mb_strlen($t) < 3) {
            $t = $title; // Fallback: original, falls zu kurz
        }
        if (mb_strlen($t) > 180) {
            $t = mb_substr($t, 0, 180);
        }
        return $t;
    }
}

?>

