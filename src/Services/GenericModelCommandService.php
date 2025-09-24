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
            $applied = false;
            foreach (['title','name'] as $candidate) {
                if (in_array($candidate, $schemaFields, true)) {
                    $query->where($candidate, 'LIKE', '%'.$q.'%');
                    // Fuzzy-Fallback via SOUNDEX zusätzlich erlauben
                    $query->orWhereRaw('SOUNDEX('.$candidate.') = SOUNDEX(?)', [$q]);
                    $applied = true;
                    break;
                }
            }
            // Generischer OR-Filter über belongsTo-FKs: Ziel label_key ~ q → FK IN (Treffer)
            $fkMap = Schemas::foreignKeys($modelKey);
            foreach ($fkMap as $fkField => $meta) {
                $targetModelKey = $meta['references'] ?? ($meta['target'] ?? null);
                if (!$targetModelKey) continue;
                $labelKey = $meta['label_key'] ?? Schemas::meta($targetModelKey, 'label_key') ?? 'name';
                $targetClass = Schemas::meta($targetModelKey, 'eloquent');
                if (!$targetClass || !class_exists($targetClass)) continue;
                try {
                    $ids = $targetClass::where($labelKey, 'LIKE', '%'.$q.'%')
                        ->orWhereRaw('SOUNDEX('.$labelKey.') = SOUNDEX(?)', [$q])
                        ->limit(25)
                        ->pluck('id')
                        ->all();
                    if (!empty($ids)) {
                        $query->orWhereIn($fkField, $ids);
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
        }
        // Filters: FK-Strings in IDs auflösen (needResolve bei Mehrtreffern)
        $fkMap = Schemas::foreignKeys($modelKey);
        $filtersInput = (array)($slots['filters'] ?? []);
        foreach ($filtersInput as $k => $v) {
            if ($v === null || $v === '') continue;
            if (array_key_exists($k, $fkMap) && is_string($v) && !ctype_digit($v)) {
                $meta = $fkMap[$k];
                $targetModelKey = $meta['references'] ?? ($meta['target'] ?? null);
                if ($targetModelKey) {
                    $labelKey = $meta['label_key'] ?? Schemas::meta($targetModelKey, 'label_key') ?? 'name';
                    $targetClass = Schemas::meta($targetModelKey, 'eloquent');
                    if ($targetClass && class_exists($targetClass)) {
                        try {
                            $matches = $targetClass::where($labelKey, 'LIKE', '%'.$v.'%')
                                ->orderByRaw('CASE WHEN '.$labelKey.' = ? THEN 0 ELSE 1 END', [$v])
                                ->orderBy($labelKey)
                                ->limit(5)
                                ->get(['id', $labelKey]);
                            if ($matches->count() === 1) {
                                $filtersInput[$k] = $matches->first()->id;
                            } elseif ($matches->count() > 1) {
                                return [
                                    'ok' => false,
                                    'message' => 'Bitte wählen: '.$k,
                                    'needResolve' => true,
                                    'choices' => $matches->map(fn($m) => ['id' => $m->id, 'label' => $m->{$labelKey} ?? (string)$m->id])->toArray(),
                                ];
                            } else {
                                return [
                                    'ok' => false,
                                    'message' => 'Referenz nicht gefunden: '.$k,
                                    'needResolve' => true,
                                ];
                            }
                        } catch (\Throwable $e) {}
                    }
                }
            }
        }
        $filters = Schemas::validateFilters($modelKey, $filtersInput);
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
        $confirmed = (bool)($slots['confirmed'] ?? false);
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
        // Titel-Guard: zu kurz oder nur Stopwörter → needResolve
        if (isset($data['title'])) {
            $t = trim((string)$data['title']);
            if (mb_strlen($t) < 3 || preg_match('/^(anlegen|aufgabe|bitte|ok|okay)$/i', $t)) {
                return ['ok' => false, 'message' => 'Titel bestätigen', 'needResolve' => true, 'missing' => array_unique(array_merge(['title'], $required)), 'data' => ['proposed' => $data]];
            }
        }
        // Due-Date Parsing (einfach, DE-Keywords)
        if (!empty($data['due_date'])) {
            $data['due_date'] = $this->parseDueDate((string)$data['due_date']);
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
        // Confirm-Gate: ohne bestätigtes Flag keine Speicherung
        if ($confirmed !== true) {
            return [
                'ok' => false,
                'message' => 'Bestätigung erforderlich',
                'needResolve' => true,
                'confirmRequired' => true,
                'data' => ['proposed' => $data, 'required' => $required],
            ];
        }
        foreach ($required as $f) {
            if (!array_key_exists($f, $data) || $data[$f] === null || $data[$f] === '') {
                return ['ok' => false, 'message' => 'Pflichtfelder fehlen', 'needResolve' => true, 'missing' => $required, 'data' => ['proposed' => $data]];
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

    protected function parseDueDate(string $input): string
    {
        $s = mb_strtolower(trim($input));
        try {
            if ($s === 'heute') return \Carbon\Carbon::today()->toDateString();
            if ($s === 'morgen') return \Carbon\Carbon::tomorrow()->toDateString();
            if ($s === 'übermorgen' || $s === 'uebermorgen') return \Carbon\Carbon::today()->addDays(2)->toDateString();
            // Versuche einfache deutsche Formate TT.MM.JJJJ
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $s, $m)) {
                $d = sprintf('%04d-%02d-%02d', (int)($m[3] < 100 ? 2000 + (int)$m[3] : (int)$m[3]), (int)$m[2], (int)$m[1]);
                return $d;
            }
            // Fallback: strtotime
            $ts = strtotime($input);
            if ($ts) return date('Y-m-d', $ts);
        } catch (\Throwable $e) {}
        return $input; // ungeändert, wenn unklar
    }
}

?>

