<?php

namespace Platform\Core\Services;

use Platform\Core\Registry\CommandRegistry;

class CommandResolver
{
    /**
     * Generischer Resolver-Fluss für "open by name"-Fälle.
     * Erwartet ein Query-Intent (z. B. planner.query) und ein Open-Intent (z. B. planner.open)
     * sowie Slot-Namen (z. B. ['q' => 'name']).
     * Gibt entweder eine direkte Navigation zurück oder eine needResolve-Auswahl.
     */
    public function resolveOpenByName(
        CommandGateway $gateway,
        string $queryIntent,
        string $openIntent,
        array $slots
    ): array {
        // 1) Query ausführen
        $limit = (int) ($slots['limit'] ?? 5);
        $q = (string) ($slots['q'] ?? ($slots['name'] ?? ''));
        $querySlots = $slots;
        $querySlots['q'] = $q;
        $querySlots['limit'] = $limit > 0 ? $limit : 5;
        $queryResult = $gateway->executeMatched([
            'command' => $this->findCommandByKey($queryIntent),
            'slots' => $querySlots,
        ], auth()->user(), true);

        $data = (array) ($queryResult['data'] ?? []);
        $items = $data['items'] ?? ($data['tasks'] ?? ($data['projects'] ?? []));
        if (!is_array($items)) {
            $items = [];
        }

        if (count($items) === 1) {
            $id = $items[0]['id'] ?? null;
            if ($id) {
                $openResult = $gateway->executeMatched([
                    'command' => $this->findCommandByKey($openIntent),
                    'slots' => ['id' => $id],
                ], auth()->user(), true);
                return $openResult;
            }
        }

        if (count($items) > 1) {
            $choices = array_slice($items, 0, 6);
            return [
                'ok' => false,
                'message' => 'Bitte wählen',
                'needResolve' => true,
                'choices' => array_map(function($it){
                    $label = $it['name'] ?? ($it['title'] ?? (string) ($it['id'] ?? ''));
                    return ['id' => $it['id'] ?? null, 'label' => $label];
                }, $choices),
            ];
        }

        // Fuzzy-Fallback: phonetische Ähnlichkeit auf Label-Feld versuchen
        try {
            $targetModelKey = (string)($slots['model'] ?? '');
            if ($targetModelKey !== '') {
                $labelKey = \Platform\Core\Schema\ModelSchemaRegistry::meta($targetModelKey, 'label_key') ?? 'name';
                $eloquent = \Platform\Core\Schema\ModelSchemaRegistry::meta($targetModelKey, 'eloquent');
                if ($eloquent && class_exists($eloquent)) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn((new $eloquent)->getTable(), $labelKey)) {
                        $fuzzy = $eloquent::whereRaw('SOUNDEX('.$labelKey.') = SOUNDEX(?)', [$q])
                            ->orderBy($labelKey)
                            ->limit(5)
                            ->get(['id', $labelKey]);
                        if ($fuzzy->count() === 1) {
                            $id = $fuzzy->first()->id;
                            $openResult = $gateway->executeMatched([
                                'command' => $this->findCommandByKey($openIntent),
                                'slots' => ['id' => $id],
                            ], auth()->user(), true);
                            return $openResult;
                        }
                        if ($fuzzy->count() > 1) {
                            $choices = $fuzzy->map(function($m) use ($labelKey){
                                return ['id' => $m->id, 'label' => $m->{$labelKey} ?? (string)$m->id];
                            })->toArray();
                            return [
                                'ok' => false,
                                'needResolve' => true,
                                'message' => 'Meintest du …?',
                                'choices' => $choices,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        return [ 'ok' => false, 'message' => 'Kein passender Eintrag gefunden' ];
    }

    protected function findCommandByKey(string $key): array
    {
        foreach (CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }
}

?>

