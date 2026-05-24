<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ObsidianVault;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SkillRegistryService
{
    public function __construct(
        private ObsidianStorageService $storage,
    ) {}

    /**
     * Index neu bauen: Frontmatter aus allen Skill-Dateien lesen + cachen.
     */
    public function rebuildIndex(int $userId, ?int $teamVaultId = null): void
    {
        // Team-Vault (nur wenn skills_enabled)
        if ($teamVaultId) {
            $vault = ObsidianVault::find($teamVaultId);
            if ($vault && $this->isSkillsEnabled($vault)) {
                $index = $this->buildIndexForVault($vault);
                Cache::put("skill_index:vault:{$teamVaultId}", $index, now()->addMinutes(15));
            }
        }

        // Persönliche Skill-Vaults
        Cache::put("skill_index:user:{$userId}", $this->buildUserIndex($userId), now()->addMinutes(15));
    }

    /**
     * Invalidiert den Skill-Cache für einen User und/oder Vault.
     */
    public function invalidateCache(int $userId, ?int $vaultId = null): void
    {
        Cache::forget("skill_index:user:{$userId}");

        if ($vaultId) {
            Cache::forget("skill_index:vault:{$vaultId}");
        }

        // Team-Vault-Resolution-Cache auch invalidieren
        Cache::forget("skill_team_vault:{$userId}");
    }

    /**
     * Suche über gecachten Index (Token/Score-Logik wie ToolRegistryService).
     *
     * @param int|null $teamVaultId Explizite Team-Vault-ID. Wenn null, werden Team-Vaults
     *                              automatisch über die team_id-Spalte erkannt.
     */
    public function search(string $query, int $userId, ?int $teamVaultId = null, int $limit = 5): array
    {
        $index = $this->getMergedIndex($userId, $teamVaultId);

        $tokens = $this->tokenize($query);
        $scored = [];

        foreach ($index as $code => $meta) {
            if (($meta['status'] ?? 'active') !== 'active') {
                continue;
            }

            $score = $this->score($meta, $tokens);

            if (!empty($tokens) && $score <= 0) {
                continue;
            }

            $scored[] = ['meta' => $meta, 'score' => $score];
        }

        usort($scored, function ($a, $b) {
            $diff = $b['score'] <=> $a['score'];
            return $diff !== 0 ? $diff : strcmp($a['meta']['code'] ?? '', $b['meta']['code'] ?? '');
        });

        $scored = array_slice($scored, 0, $limit);

        return array_map(fn($item) => $this->formatCompact($item['meta']), $scored);
    }

    /**
     * Einzelnen Skill laden (Frontmatter + Body).
     */
    public function get(string $code, int $userId, ?int $teamVaultId = null): ?array
    {
        $index = $this->getMergedIndex($userId, $teamVaultId);

        if (!isset($index[$code])) {
            return null;
        }

        $meta = $index[$code];

        // Body aus Vault laden
        $vault = ObsidianVault::find($meta['_vault_id']);
        if (!$vault) {
            return null;
        }

        $bodyMarkdown = null;
        try {
            $content = $this->storage->readFile($vault, $meta['_file_path']);
            $bodyMarkdown = $this->extractBody($content);
        } catch (\Throwable $e) {
            Log::warning('[SkillRegistry] Konnte Skill-Body nicht laden', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->formatFull($meta, $bodyMarkdown);
    }

    /**
     * Gibt nur die gecachten Skill-Codes zurück. Kein Vault-Scan — nur Cache-Read.
     * Wenn kein Cache vorhanden, leeres Array (der nächste SEARCH/GET baut den Cache).
     *
     * @return array<string> Liste der Skill-Codes
     */
    public function getCachedCodes(int $userId, ?int $teamVaultId = null): array
    {
        $codes = [];

        $personalIndex = Cache::get("skill_index:user:{$userId}");
        if (is_array($personalIndex)) {
            $codes = array_keys($personalIndex);
        }

        if ($teamVaultId) {
            $teamIndex = Cache::get("skill_index:vault:{$teamVaultId}");
            if (is_array($teamIndex)) {
                $codes = array_merge($codes, array_keys($teamIndex));
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Findet die Vault-ID des Team-Skill-Vaults für ein gegebenes Team.
     * Gecacht für 15 Minuten um DB-Queries zu sparen.
     */
    public function resolveTeamVaultId(?object $team): ?int
    {
        if (!$team || !isset($team->id)) {
            return null;
        }

        return Cache::remember("skill_team_vault:team:{$team->id}", now()->addMinutes(15), function () use ($team) {
            $vault = ObsidianVault::where('team_id', $team->id)
                ->get()
                ->first(fn(ObsidianVault $v) => $this->isSkillsEnabled($v));

            return $vault?->id;
        });
    }

    /**
     * Merged Index: Team-Skills zuerst, dann persönliche. Duplikate → Team gewinnt.
     */
    private function getMergedIndex(int $userId, ?int $teamVaultId = null): array
    {
        $merged = [];

        // 1. Persönliche Skills
        $personalIndex = Cache::get("skill_index:user:{$userId}");
        if ($personalIndex === null) {
            $personalIndex = $this->buildUserIndex($userId);
            Cache::put("skill_index:user:{$userId}", $personalIndex, now()->addMinutes(15));
        }
        foreach ($personalIndex as $code => $meta) {
            $merged[$code] = $meta;
        }

        // 2. Team-Vault-Skills (überschreiben persönliche bei Duplikaten, nur wenn skills_enabled)
        if ($teamVaultId) {
            $teamIndex = Cache::get("skill_index:vault:{$teamVaultId}");
            if ($teamIndex === null) {
                $vault = ObsidianVault::find($teamVaultId);
                if ($vault && $this->isSkillsEnabled($vault)) {
                    $teamIndex = $this->buildIndexForVault($vault);
                    Cache::put("skill_index:vault:{$teamVaultId}", $teamIndex, now()->addMinutes(15));
                } else {
                    $teamIndex = [];
                }
            }
            foreach ($teamIndex as $code => $meta) {
                $merged[$code] = $meta; // Team gewinnt
            }
        }

        return $merged;
    }

    /**
     * Index aller persönlichen Skill-Vaults eines Users bauen (nur Vaults ohne team_id mit skills_enabled).
     */
    private function buildUserIndex(int $userId): array
    {
        $userVaults = ObsidianVault::where('user_id', $userId)
            ->whereNull('team_id')
            ->get()
            ->filter(fn(ObsidianVault $v) => $this->isSkillsEnabled($v));

        $index = [];
        foreach ($userVaults as $vault) {
            $index = array_merge($index, $this->buildIndexForVault($vault));
        }
        return $index;
    }

    /**
     * Prüft ob ein Vault als Skill-Vault konfiguriert ist.
     * Robust gegen verschiedene Typen (bool, string "1", "true", etc.).
     */
    private function isSkillsEnabled(ObsidianVault $vault): bool
    {
        $settings = $vault->settings;
        if (!is_array($settings)) {
            return false;
        }

        $value = $settings['skills_enabled'] ?? false;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Scannt skills/-Ordner eines Vaults und parsed Frontmatter.
     * Liest jede Datei nur einmal (Frontmatter + Body-Preview aus demselben Read).
     *
     * @return array<string, array> Code => Metadata
     */
    private function buildIndexForVault(ObsidianVault $vault): array
    {
        $index = [];

        try {
            $files = $this->storage->listFiles($vault, 'skills');

            foreach ($files as $file) {
                if ($file['type'] !== 'file') {
                    continue;
                }
                if (!str_ends_with($file['path'], '.md')) {
                    continue;
                }

                try {
                    // Ein einziger S3-Read pro Datei
                    $content = $this->storage->readFile($vault, $file['path']);

                    $frontmatter = $this->parseFrontmatterFromContent($content);
                    if (!$frontmatter || empty($frontmatter['code'])) {
                        continue;
                    }

                    $code = $frontmatter['code'];

                    // Body-Preview aus demselben Content
                    $bodyPreview = '';
                    $body = $this->extractBody($content);
                    if ($body) {
                        $bodyPreview = mb_substr($body, 0, 200);
                    }

                    $index[$code] = [
                        'code' => $code,
                        'name' => $frontmatter['name'] ?? $code,
                        'intent' => $frontmatter['intent'] ?? '',
                        'trigger_phrases' => (array) ($frontmatter['trigger_phrases'] ?? []),
                        'required_tools' => (array) ($frontmatter['required_tools'] ?? []),
                        'tier' => $frontmatter['tier'] ?? 'common',
                        'status' => $frontmatter['status'] ?? 'active',
                        'tags' => (array) ($frontmatter['tags'] ?? []),
                        'body_preview' => $bodyPreview,
                        'vault_source' => $vault->isTeamVault() ? 'team' : 'personal',
                        '_vault_id' => $vault->id,
                        '_file_path' => $file['path'],
                    ];
                } catch (\Throwable $e) {
                    Log::warning('[SkillRegistry] Skill-Datei konnte nicht geparst werden', [
                        'vault_id' => $vault->id,
                        'path' => $file['path'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[SkillRegistry] Vault-Scan fehlgeschlagen', [
                'vault_id' => $vault->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $index;
    }

    /**
     * Parsed YAML-Frontmatter aus einem bereits gelesenen Content-String.
     * Vermeidet einen zweiten S3-Read (im Gegensatz zu ObsidianStorageService::parseFrontmatter).
     */
    private function parseFrontmatterFromContent(string $content): ?array
    {
        // Strip BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $content = ltrim($content);

        if (!str_starts_with($content, "---\n")) {
            return null;
        }

        $endPos = strpos($content, "\n---", 3);
        if ($endPos === false) {
            return null;
        }

        $yaml = substr($content, 4, $endPos - 4);

        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse(trim($yaml));
            return is_array($parsed) ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

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
     *   code exact match      +10
     *   code contains         +6
     *   trigger_phrase match  +8
     *   name contains         +5
     *   intent contains       +4
     *   tag match             +6
     *   body_preview contains +1
     */
    private function score(array $meta, array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $score = 0;
        $codeLower = mb_strtolower($meta['code'] ?? '');
        $nameLower = mb_strtolower($meta['name'] ?? '');
        $intentLower = mb_strtolower($meta['intent'] ?? '');
        $bodyPreviewLower = mb_strtolower($meta['body_preview'] ?? '');
        $tagsLower = array_map('mb_strtolower', $meta['tags'] ?? []);
        $triggerPhrasesLower = array_map('mb_strtolower', $meta['trigger_phrases'] ?? []);

        foreach ($tokens as $token) {
            // Code-Match
            if ($codeLower === $token) {
                $score += 10;
            } elseif (str_contains($codeLower, $token)) {
                $score += 6;
            }

            // Trigger-Phrase-Match
            foreach ($triggerPhrasesLower as $phrase) {
                if (str_contains($phrase, $token)) {
                    $score += 8;
                    break;
                }
            }

            // Name-Match
            if (str_contains($nameLower, $token)) {
                $score += 5;
            }

            // Intent-Match
            if (str_contains($intentLower, $token)) {
                $score += 4;
            }

            // Tag-Match
            foreach ($tagsLower as $tag) {
                if (str_contains($tag, $token)) {
                    $score += 6;
                    break;
                }
            }

            // Body-Preview-Match
            if (str_contains($bodyPreviewLower, $token)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Extrahiert den Markdown-Body (alles nach dem zweiten ---).
     */
    private function extractBody(string $content): ?string
    {
        // Strip BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $content = ltrim($content);

        if (!str_starts_with($content, "---\n")) {
            return $content;
        }

        $endPos = strpos($content, "\n---", 3);
        if ($endPos === false) {
            return null;
        }

        // Body beginnt nach dem schließenden ---\n
        $bodyStart = $endPos + 4; // "\n---" = 4 chars
        if (isset($content[$bodyStart]) && $content[$bodyStart] === "\n") {
            $bodyStart++;
        }

        return trim(substr($content, $bodyStart));
    }

    /**
     * Token-sparendes Kompaktformat für Search-Results.
     */
    private function formatCompact(array $meta): array
    {
        $result = [
            'code' => $meta['code'],
            'name' => $meta['name'],
            'intent' => $meta['intent'],
            'tier' => $meta['tier'],
            'status' => $meta['status'],
        ];

        if (!empty($meta['trigger_phrases'])) {
            $result['trigger_phrases'] = $meta['trigger_phrases'];
        }

        if (!empty($meta['required_tools'])) {
            $result['required_tools'] = $meta['required_tools'];
        }

        if (!empty($meta['tags'])) {
            $result['tags'] = $meta['tags'];
        }

        return $result;
    }

    /**
     * Volles Format für Einzel-Abfragen.
     */
    private function formatFull(array $meta, ?string $bodyMarkdown = null): array
    {
        $result = $this->formatCompact($meta);
        $result['vault_source'] = $meta['vault_source'] ?? 'personal';
        $result['file_path'] = $meta['_file_path'] ?? null;

        if ($bodyMarkdown !== null) {
            $result['body_markdown'] = $bodyMarkdown;
        }

        return $result;
    }
}
