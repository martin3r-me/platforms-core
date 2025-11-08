<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\HasTimeAncestors;

class TimeContextResolver
{
    /**
     * Lädt das Modell und gibt dessen Vorfahren zurück.
     *
     * @param string $type Model-Klasse
     * @param int $id Model-ID
     * @return array Array von Vorfahren-Kontexten
     */
    public function resolveAncestors(string $type, int $id): array
    {
        if (! class_exists($type)) {
            return [];
        }

        $model = $type::find($id);

        if (! $model) {
            return [];
        }

        if (! $model instanceof HasTimeAncestors) {
            return [];
        }

        return $model->timeAncestors();
    }

    /**
     * Erstellt einen Kontext-Label aus dem Modell.
     *
     * @param string $type Model-Klasse
     * @param int $id Model-ID
     * @return string|null
     */
    public function resolveLabel(string $type, int $id): ?string
    {
        if (! class_exists($type)) {
            return null;
        }

        $model = $type::find($id);

        if (! $model) {
            return null;
        }

        // Versuche verschiedene Label-Felder
        if (isset($model->name)) {
            return $model->name;
        }

        if (isset($model->title)) {
            return $model->title;
        }

        if (method_exists($model, '__toString')) {
            return (string) $model;
        }

        return null;
    }
}

