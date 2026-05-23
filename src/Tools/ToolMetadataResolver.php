<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;

/**
 * Leitet Metadaten automatisch aus einem ToolContract ab.
 *
 * Statische Tool-Metadaten leben im Code, nicht in der DB.
 * Diese Klasse extrahiert name, kind, namespace, tier, intent, tags etc.
 * aus dem Tool selbst — ohne dass die ~1.380 Tool-Klassen geändert werden müssen.
 */
class ToolMetadataResolver
{
    /** Kinds die als read_only gelten */
    private const READ_ONLY_KINDS = ['GET', 'SEARCH', 'FETCH', 'READ', 'FRONTMATTER', 'LIST', 'BATCH_READ'];

    /** @var array<string> */
    private array $discoveryToolNames;

    /**
     * @param array<string> $discoveryToolNames Tool-Namen die als always_on gelten
     */
    public function __construct(array $discoveryToolNames = [])
    {
        $this->discoveryToolNames = $discoveryToolNames;
    }

    /**
     * Gibt ein vollständiges Metadata-Array für ein Tool zurück.
     */
    public function resolve(ToolContract $tool): array
    {
        $name = $tool->getName();
        $segments = explode('.', $name);
        $description = $tool->getDescription();

        // Auto-derived defaults
        $kind = $this->deriveKind($segments);
        $namespace = $segments[0] ?? $name;
        $module = $namespace;
        $intent = $this->deriveIntent($description);
        $tier = in_array($name, $this->discoveryToolNames, true) ? 'always_on' : 'common';
        $costClass = 'local_db';
        $readOnly = in_array($kind, self::READ_ONLY_KINDS, true);
        $tags = $this->deriveTags($segments);

        // Schema-derived params
        $schema = $tool->getSchema();
        [$requiredParams, $optionalParams] = $this->deriveParams($schema);

        $meta = [
            'name' => $name,
            'kind' => $kind,
            'namespace' => $namespace,
            'module' => $module,
            'intent' => $intent,
            'description' => $description,
            'tier' => $tier,
            'cost_class' => $costClass,
            'read_only' => $readOnly,
            'deprecated' => false,
            'successor_name' => null,
            'cost_per_call_eur' => null,
            'tags' => $tags,
            'required_params' => $requiredParams,
            'optional_params' => $optionalParams,
        ];

        // ToolMetadataContract kann Defaults überschreiben
        if ($tool instanceof ToolMetadataContract) {
            $meta = $this->applyExplicitMetadata($meta, $tool->getMetadata());
        }

        return $meta;
    }

    /**
     * Letztes Segment des Namens als Kind.
     * planner.tasks.POST → POST
     */
    private function deriveKind(array $segments): string
    {
        return end($segments) ?: 'UNKNOWN';
    }

    /**
     * Erster Satz der Description als Intent.
     */
    private function deriveIntent(string $description): string
    {
        // Erster Satz: bis zum ersten Punkt gefolgt von Whitespace oder Ende
        if (preg_match('/^(.+?\.)\s/u', $description, $m)) {
            return mb_substr($m[1], 0, 200);
        }

        return mb_substr($description, 0, 200);
    }

    /**
     * Name-Segmente als Tags (ohne letztes = kind).
     */
    private function deriveTags(array $segments): array
    {
        // Alle Segmente außer dem letzten (kind) als Tags
        $tags = array_slice($segments, 0, -1);

        return array_values(array_unique(array_filter($tags)));
    }

    /**
     * Extrahiert required/optional params aus dem JSON Schema.
     *
     * @return array{0: array, 1: array} [required_params, optional_params]
     */
    private function deriveParams(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $requiredParams = [];
        $optionalParams = [];

        foreach ($properties as $paramName => $paramSchema) {
            $param = [
                'name' => $paramName,
                'type' => $paramSchema['type'] ?? 'string',
            ];
            if (!empty($paramSchema['description'])) {
                $param['description'] = $paramSchema['description'];
            }

            if (in_array($paramName, $required, true)) {
                $requiredParams[] = $param;
            } else {
                $optionalParams[] = $param;
            }
        }

        return [$requiredParams, $optionalParams];
    }

    /**
     * Überschreibt auto-derived Werte mit expliziten Metadaten aus ToolMetadataContract.
     */
    private function applyExplicitMetadata(array $meta, array $explicit): array
    {
        // Direkt übernehmbare Keys
        $directKeys = ['kind', 'namespace', 'module', 'intent', 'tier', 'cost_class', 'read_only', 'deprecated', 'successor_name', 'cost_per_call_eur'];

        foreach ($directKeys as $key) {
            if (array_key_exists($key, $explicit)) {
                $meta[$key] = $explicit[$key];
            }
        }

        // Tags: mergen statt ersetzen
        if (!empty($explicit['tags'])) {
            $meta['tags'] = array_values(array_unique(array_merge($meta['tags'], (array) $explicit['tags'])));
        }

        return $meta;
    }
}
