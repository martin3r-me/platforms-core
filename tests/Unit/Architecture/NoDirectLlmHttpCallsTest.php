<?php

namespace Platform\Core\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Architektur-Guardrail: Modell-Zugriff läuft über die Nähte, nicht ad hoc.
 *
 * Kein Feature darf einen eigenen HTTP-Call direkt an api.anthropic.com oder
 * api.openai.com bauen. Genau diese streunenden Transport-Stellen (eigener Key,
 * eigener Endpoint, eigenes Retry) sind die Quelle späterer Fehler — Key-/
 * Endpoint-Drift, kein zentrales Logging, kein zentraler Fallback.
 *
 * Sanktionierte Stellen (Allowlist unten) sind:
 *   - die Core-Provider / -Services selbst (DIE Chat-/Embedding-Naht),
 *   - bewusst separate Modalitäts-Nähte (Bilder, TTS),
 *   - reine Diagnose-/Sync-Pfade (Health-Check, Env-Pull, Model-Sync),
 *   - die ai-assistant OpenAI-Assistants-API (anderes Paradigma, eigene Naht).
 *
 * Taucht ein NEUER Direkt-Call außerhalb der Allowlist auf, schlägt dieser Test
 * fehl und nennt die Datei. Dann gilt: über die passende Naht routen —
 * {@see \Platform\Core\Services\LLMProviderRegistry} (Chat) bzw.
 * {@see \Platform\Core\Services\EmbeddingProviderRegistry} (Embeddings) —
 * oder, mit Begründung, hier eintragen.
 *
 * Bewusst dependency-frei (reiner Datei-Scan, kein Framework-Boot), damit er
 * überall läuft, wo die Suite läuft.
 */
class NoDirectLlmHttpCallsTest extends TestCase
{
    /** Hosts, deren direkter Aufruf nur in den sanktionierten Dateien erlaubt ist. */
    private const FORBIDDEN_HOSTS = [
        'https://api.anthropic.com',
        'https://api.openai.com',
    ];

    /**
     * Repo-relative Pfade, in denen der Direkt-Call bewusst lebt.
     * Jede Zeile ist eine Entscheidung mit Grund — nicht blind erweitern.
     */
    private const ALLOWLIST = [
        // --- Chat-Naht (DIE eine Transport-Stelle je Anbieter) ---
        'core/src/Services/AnthropicProvider.php',
        'core/src/Services/OpenAiProvider.php',
        'core/src/Services/OpenAiService.php',

        // --- Embedding-Naht ---
        'core/src/Services/OpenAiEmbeddingProvider.php',

        // --- Eigene Modalitäts-Nähte (nicht Chat) ---
        'core/src/Services/ImageGenerationService.php',   // Bildgenerierung
        'modules/vocab/src/Services/TtsService.php',      // TTS
        'modules/vocab/src/Tools/TextToSpeechTool.php',   // TTS (dupliziert mit TtsService — Dedup offen)

        // --- Diagnose / Sync (Management-Pfade, keine Feature-Inferenz) ---
        'core/src/Console/Commands/SyncAiModelsCommand.php',
        'modules/organization/src/Tools/InferenceHealthCheckTool.php',
        'modules/organization/src/Services/EnvironmentPullService.php',

        // --- ai-assistant: OpenAI Assistants-API v2 (stateful threads/runs) ---
        // Anderes Paradigma als chat() — bewusst eigene Naht, nicht die Registry.
        'modules/ai-assistant/config/ai-assistant.php',
        'modules/ai-assistant/src/Services/OpenAIModelService.php',
        'modules/ai-assistant/src/Services/RunService.php',
        'modules/ai-assistant/src/Services/ThreadService.php',
        'modules/ai-assistant/src/Services/RunService Kopie.php', // TODO tote Datei, entfernen
    ];

    public function test_no_direct_llm_http_calls_outside_allowlist(): void
    {
        $root = $this->repoRoot();
        $allowlist = array_fill_keys(self::ALLOWLIST, true);

        $violations = [];
        foreach ($this->scanDirs($root) as $absPath) {
            $contents = @file_get_contents($absPath);
            if ($contents === false) {
                continue;
            }
            foreach (self::FORBIDDEN_HOSTS as $host) {
                if (str_contains($contents, $host)) {
                    $rel = $this->relative($root, $absPath);
                    if (! isset($allowlist[$rel])) {
                        $violations[$rel][] = $host;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Direkter LLM-HTTP-Call außerhalb der sanktionierten Nähte gefunden.\n"
            . "Route über LLMProviderRegistry (Chat) bzw. EmbeddingProviderRegistry (Embeddings)\n"
            . "— oder trage die Datei mit Begründung in NoDirectLlmHttpCallsTest::ALLOWLIST ein.\n"
            . "Betroffen:\n  " . implode("\n  ", array_keys($violations))
        );
    }

    /**
     * Findet die Repo-Wurzel: der erste Vorfahr, der sowohl core/ als auch
     * modules/ enthält (Monorepo). Fällt auf das core-Paket zurück, wenn die
     * Geschwister-Repos nicht danebenliegen (core isoliert als vendor-Paket).
     */
    private function repoRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== dirname($dir)) {
            if (is_dir($dir . '/core') && is_dir($dir . '/modules')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // Fallback: core-Paketwurzel (…/core) → scannt nur core/src.
        return dirname(__DIR__, 3);
    }

    /**
     * Die zu scannenden Quellbäume, sofern vorhanden.
     *
     * @return iterable<string> absolute .php-Pfade
     */
    private function scanDirs(string $root): iterable
    {
        $targets = ['core/src', 'modules', 'services'];
        foreach ($targets as $target) {
            $path = $root . '/' . $target;
            if (! is_dir($path)) {
                continue;
            }
            yield from $this->phpFiles($path);
        }
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $dir): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current) {
                    // vendor/node_modules/Tests nicht durchsuchen.
                    $name = $current->getFilename();
                    if ($current->isDir()) {
                        return ! in_array($name, ['vendor', 'node_modules', 'tests', 'Tests'], true);
                    }
                    return str_ends_with($name, '.php');
                }
            )
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }

    private function relative(string $root, string $absPath): string
    {
        return ltrim(str_replace($root, '', $absPath), '/');
    }
}
