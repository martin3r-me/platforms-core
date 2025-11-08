<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Models\CoreTimePlanned;
use Platform\Core\Models\CoreTimePlannedContext;

class StorePlannedTime
{
    public function __construct(
        protected TimeContextResolver $resolver
    ) {
    }

    /**
     * Erstellt einen neuen Planned-Time-Eintrag mit automatischer Kontext-Kaskade.
     *
     * @param array $data Planned-Daten (team_id, user_id, context_type, context_id, planned_minutes, note, is_active)
     * @return CoreTimePlanned
     */
    public function store(array $data): CoreTimePlanned
    {
        return DB::transaction(function () use ($data) {
            // 1. Planned-Time-Eintrag erstellen
            $planned = CoreTimePlanned::create([
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'context_type' => $data['context_type'],
                'context_id' => $data['context_id'],
                'planned_minutes' => $data['planned_minutes'],
                'note' => $data['note'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 2. Primärkontext anlegen (depth=0, is_primary=true)
            $primaryLabel = $this->resolver->resolveLabel($data['context_type'], $data['context_id']);
            CoreTimePlannedContext::updateOrCreate(
                [
                    'planned_id' => $planned->id,
                    'context_type' => $data['context_type'],
                    'context_id' => $data['context_id'],
                ],
                [
                    'depth' => 0,
                    'is_primary' => true,
                    'is_root' => false,
                    'context_label' => $primaryLabel,
                ]
            );

            // 3. Vorfahren-Kontexte auflösen und anlegen
            $ancestors = $this->resolver->resolveAncestors($data['context_type'], $data['context_id']);

            foreach ($ancestors as $depth => $ancestor) {
                $ancestorDepth = $depth + 1;
                $isRoot = $ancestor['is_root'] ?? false;
                $ancestorLabel = $ancestor['label'] ?? $this->resolver->resolveLabel($ancestor['type'], $ancestor['id']);

                CoreTimePlannedContext::updateOrCreate(
                    [
                        'planned_id' => $planned->id,
                        'context_type' => $ancestor['type'],
                        'context_id' => $ancestor['id'],
                    ],
                    [
                        'depth' => $ancestorDepth,
                        'is_primary' => false,
                        'is_root' => $isRoot,
                        'context_label' => $ancestorLabel,
                    ]
                );
            }

            return $planned->fresh();
        });
    }
}

