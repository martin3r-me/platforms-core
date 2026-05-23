<?php

namespace Platform\Core\Services;

use Platform\Core\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

class ToolRegistryService
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolPermissionService $permissionService,
    ) {}

    /**
     * Suche nach Tools mit Multi-Wort-Tokenisierung und lexikalischem Scoring.
     *
     * @param string $query Natürlichsprachliche Suchanfrage
     * @param array  $filters {namespace?, tier?, cost_class?, kind?, deprecated?, name_glob?}
     * @param int    $limit Max Ergebnisse
     * @return array Token-sparendes Response-Format
     */
    public function search(string $query = '', array $filters = [], int $limit = 5): array
    {
        $index = $this->registry->getIndex();

        // Filter anwenden
        $index = $this->applyFilters($index, $filters);

        // Permission-Filter
        $index = array_filter($index, fn(array $meta) => $this->permissionService->hasAccess($meta['name']));

        // Scoring
        $tokens = $this->tokenize($query);
        $scored = [];

        foreach ($index as $name => $meta) {
            $score = $this->score($meta, $tokens);

            // Bei leerer Query ohne Tokens: alle zeigen (score 0)
            // Bei Query mit Tokens: nur Treffer mit score > 0
            if (!empty($tokens) && $score <= 0) {
                continue;
            }

            $scored[] = ['meta' => $meta, 'score' => $score];
        }

        // Nach Score sortieren (absteigend), bei Gleichstand nach Name
        usort($scored, function ($a, $b) {
            $diff = $b['score'] <=> $a['score'];
            return $diff !== 0 ? $diff : strcmp($a['meta']['name'], $b['meta']['name']);
        });

        // Limit anwenden
        $scored = array_slice($scored, 0, $limit);

        return array_map(fn($item) => $this->formatCompact($item['meta']), $scored);
    }

    /**
     * Einzelnes Tool laden (volle Details).
     */
    public function get(string $name): ?array
    {
        $index = $this->registry->getIndex();

        if (!isset($index[$name])) {
            return null;
        }

        if (!$this->permissionService->hasAccess($name)) {
            return null;
        }

        return $this->formatFull($index[$name]);
    }

    // --- Tokenization & Scoring ---

    /**
     * Tokenisiert eine Suchanfrage in Einzelwörter.
     *
     * @return array<string> Lowercase-Tokens, min 2 Zeichen
     */
    private function tokenize(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        $words = preg_split('/\s+/u', mb_strtolower(trim($query)));

        return array_values(array_filter($words, fn(string $w) => mb_strlen($w) >= 2));
    }

    /**
     * Scored ein Metadata-Array gegen eine Token-Liste.
     *
     * Pro Token:
     *   name exact match  +10
     *   name contains     +6
     *   tag match         +8
     *   intent contains   +5
     *   description cont. +2
     */
    private function score(array $meta, array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $score = 0;
        $nameLower = mb_strtolower($meta['name'] ?? '');
        $intentLower = mb_strtolower($meta['intent'] ?? '');
        $descLower = mb_strtolower($meta['description'] ?? '');
        $tagsLower = array_map('mb_strtolower', $meta['tags'] ?? []);

        foreach ($tokens as $token) {
            // Name-Match
            if ($nameLower === $token) {
                $score += 10;
            } elseif (str_contains($nameLower, $token)) {
                $score += 6;
            }

            // Tag-Match
            foreach ($tagsLower as $tag) {
                if (str_contains($tag, $token)) {
                    $score += 8;
                    break;
                }
            }

            // Intent-Match
            if (str_contains($intentLower, $token)) {
                $score += 5;
            }

            // Description-Match
            if (str_contains($descLower, $token)) {
                $score += 2;
            }
        }

        return $score;
    }

    // --- Filters ---

    /**
     * Wendet Array-Filter auf den Index an.
     *
     * @param array<string, array> $index
     * @param array $filters
     * @return array<string, array>
     */
    private function applyFilters(array $index, array $filters): array
    {
        // name_glob: fnmatch()-basierter Filter
        if (!empty($filters['name_glob'])) {
            $glob = $filters['name_glob'];
            $index = array_filter($index, fn(array $meta) => fnmatch($glob, $meta['name'], FNM_CASEFOLD));
        }

        if (!empty($filters['namespace'])) {
            $ns = $filters['namespace'];
            $index = array_filter($index, fn(array $meta) => ($meta['namespace'] ?? '') === $ns);
        }

        if (!empty($filters['tier'])) {
            $tier = $filters['tier'];
            $index = array_filter($index, fn(array $meta) => ($meta['tier'] ?? '') === $tier);
        }

        if (!empty($filters['cost_class'])) {
            $cc = $filters['cost_class'];
            $index = array_filter($index, fn(array $meta) => ($meta['cost_class'] ?? '') === $cc);
        }

        if (!empty($filters['kind'])) {
            $kind = $filters['kind'];
            $index = array_filter($index, fn(array $meta) => ($meta['kind'] ?? '') === $kind);
        }

        // Deprecated-Filter (Standard: keine deprecaten zeigen)
        $showDeprecated = $filters['deprecated'] ?? false;
        if (!$showDeprecated) {
            $index = array_filter($index, fn(array $meta) => !($meta['deprecated'] ?? false));
        }

        return $index;
    }

    // --- Formatters ---

    /**
     * Token-sparendes Kompaktformat für Search-Results.
     */
    private function formatCompact(array $meta): array
    {
        $result = [
            'name' => $meta['name'],
            'intent' => $meta['intent'],
            'kind' => $meta['kind'],
            'tier' => $meta['tier'],
            'cost_class' => $meta['cost_class'],
            'namespace' => $meta['namespace'],
            'read_only' => $meta['read_only'],
        ];

        if (!empty($meta['required_params'])) {
            $result['required_params'] = array_map(
                fn($p) => ['name' => $p['name'] ?? '', 'type' => $p['type'] ?? 'string'],
                $meta['required_params']
            );
        }

        if (!empty($meta['tags'])) {
            $result['tags'] = $meta['tags'];
        }

        return $result;
    }

    /**
     * Volles Format für Einzel-Abfragen.
     */
    private function formatFull(array $meta): array
    {
        $result = $this->formatCompact($meta);
        $result['description'] = $meta['description'];
        $result['module'] = $meta['module'];
        $result['optional_params'] = $meta['optional_params'] ?? [];
        $result['deprecated'] = $meta['deprecated'] ?? false;
        $result['successor_name'] = $meta['successor_name'] ?? null;
        $result['cost_per_call_eur'] = $meta['cost_per_call_eur'] ?? null;

        return $result;
    }
}
