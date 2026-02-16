<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Tool zum Lesen und Durchsuchen der laravel.log
 *
 * Ermöglicht Debugging direkt aus dem Chat heraus, ohne SSH-Zugang.
 * Unterstützt: Tail, Grep/Search, Level-Filter, Zeitraum-Filter, Stacktrace-Parsing.
 *
 * Sicherheit:
 * - Zugriff per ENV LOG_TOOL_ALLOWED_USERS (kommaseparierte User-IDs, Default: 21)
 * - Sensible Daten (Passwörter, Tokens, API-Keys) werden maskiert
 * - Output-Limit zum Schutz des LLM-Kontexts
 */
class GetLogTool implements ToolContract
{
    /**
     * Maximale Zeichenanzahl pro Response (schützt LLM-Kontext)
     */
    private const MAX_OUTPUT_CHARS = 30000;

    /**
     * Maximale Zeilen die aus der Datei gelesen werden (Performance-Schutz)
     */
    private const MAX_READ_LINES = 5000;

    /**
     * Default Anzahl Zeilen für Tail
     */
    private const DEFAULT_TAIL_LINES = 50;

    /**
     * Patterns für sensible Daten die maskiert werden
     */
    private const SENSITIVE_PATTERNS = [
        // Passwörter in verschiedenen Formaten
        '/("password"\s*[:=]\s*")[^"]+(")/i'          => '$1***MASKED***$2',
        "/(password\s*[:=]\s*')[^']+(')/i"            => '$1***MASKED***$2',
        '/("passwd"\s*[:=]\s*")[^"]+(")/i'             => '$1***MASKED***$2',
        '/("secret"\s*[:=]\s*")[^"]+(")/i'             => '$1***MASKED***$2',

        // API Keys und Tokens
        '/("api_key"\s*[:=]\s*")[^"]+(")/i'            => '$1***MASKED***$2',
        '/("apikey"\s*[:=]\s*")[^"]+(")/i'             => '$1***MASKED***$2',
        '/("api_secret"\s*[:=]\s*")[^"]+(")/i'         => '$1***MASKED***$2',
        '/("token"\s*[:=]\s*")[^"]+(")/i'              => '$1***MASKED***$2',
        '/("access_token"\s*[:=]\s*")[^"]+(")/i'       => '$1***MASKED***$2',
        '/("refresh_token"\s*[:=]\s*")[^"]+(")/i'      => '$1***MASKED***$2',
        '/("bearer\s+)[A-Za-z0-9\-._~+\/]+=*/i'       => '$1***MASKED***',
        '/("authorization"\s*[:=]\s*")[^"]+(")/i'      => '$1***MASKED***$2',

        // Datenbank-Credentials
        '/("DB_PASSWORD"\s*[:=]\s*")[^"]+(")/i'        => '$1***MASKED***$2',
        '/(DB_PASSWORD\s*=\s*)\S+/i'                   => '$1***MASKED***',

        // AWS / Cloud Keys
        '/(AKIA[0-9A-Z]{16})/i'                        => '***AWS_KEY_MASKED***',
        '/("aws_secret"\s*[:=]\s*")[^"]+(")/i'         => '$1***MASKED***$2',

        // E-Mail-Adressen teilweise maskieren (Datenschutz)
        // Bewusst NICHT maskiert – Log-Debugging braucht oft E-Mail-Kontext
    ];

    public function getName(): string
    {
        return 'core.log.GET';
    }

    public function getDescription(): string
    {
        return 'Liest und durchsucht die laravel.log. Ermöglicht Debugging direkt aus dem Chat: '
            . 'Letzte Zeilen lesen (Tail), nach Pattern suchen (Grep), nach Log-Level filtern '
            . '(ERROR, WARNING, INFO, DEBUG), nach Zeitraum filtern (since/until). '
            . 'Multiline-Einträge (Exceptions + Stacktraces) werden als zusammenhängende Blöcke geliefert. '
            . 'Zugriff ist auf autorisierte User beschränkt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lines' => [
                    'type' => 'integer',
                    'description' => 'Anzahl der letzten Zeilen (Tail). Standard: 50, Maximum: 500.',
                    'minimum' => 1,
                    'maximum' => 500,
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Suchbegriff oder Pattern (Grep). Durchsucht den gesamten Log-Eintrag inkl. Stacktrace. Beispiele: "Exception", "SQLSTATE", "Class\\\\Name", "OutOfMemory".',
                ],
                'level' => [
                    'type' => 'string',
                    'enum' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
                    'description' => 'Nach Log-Level filtern. Zeigt nur Einträge mit diesem Level.',
                ],
                'since' => [
                    'type' => 'string',
                    'description' => 'Einträge ab diesem Zeitpunkt (inklusiv). Format: "YYYY-MM-DD" oder "YYYY-MM-DD HH:MM:SS". Beispiel: "2025-01-15 14:00:00".',
                ],
                'until' => [
                    'type' => 'string',
                    'description' => 'Einträge bis zu diesem Zeitpunkt (inklusiv). Format: "YYYY-MM-DD" oder "YYYY-MM-DD HH:MM:SS". Beispiel: "2025-01-15 15:00:00".',
                ],
                'max_chars' => [
                    'type' => 'integer',
                    'description' => 'Maximale Zeichenanzahl der Ausgabe (Token-Budget). Standard: 30000, Maximum: 50000.',
                    'minimum' => 1000,
                    'maximum' => 50000,
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // 1. Zugriffskontrolle
            $accessCheck = $this->checkAccess($context);
            if ($accessCheck !== null) {
                return $accessCheck;
            }

            // 2. Log-Datei finden
            $logPath = $this->resolveLogPath();
            if ($logPath === null) {
                return ToolResult::error('LOG_NOT_FOUND', 'Die laravel.log wurde nicht gefunden. Geprüfte Pfade: storage/logs/laravel.log');
            }

            // 3. Parameter auslesen
            $requestedLines = min((int) ($arguments['lines'] ?? self::DEFAULT_TAIL_LINES), 500);
            $search = $arguments['search'] ?? null;
            $level = $arguments['level'] ?? null;
            $since = $arguments['since'] ?? null;
            $until = $arguments['until'] ?? null;
            $maxChars = min((int) ($arguments['max_chars'] ?? self::MAX_OUTPUT_CHARS), 50000);

            // 4. Zeitstempel parsen
            $sinceTime = $since ? $this->parseTimestamp($since) : null;
            $untilTime = $until ? $this->parseTimestamp($until) : null;

            if ($since && $sinceTime === null) {
                return ToolResult::error('INVALID_TIMESTAMP', "Ungültiges 'since'-Format: \"{$since}\". Erwartet: YYYY-MM-DD oder YYYY-MM-DD HH:MM:SS");
            }
            if ($until && $untilTime === null) {
                return ToolResult::error('INVALID_TIMESTAMP', "Ungültiges 'until'-Format: \"{$until}\". Erwartet: YYYY-MM-DD oder YYYY-MM-DD HH:MM:SS");
            }

            // 5. Log-Datei lesen und parsen
            $rawLines = $this->tailFile($logPath, self::MAX_READ_LINES);
            if (empty($rawLines)) {
                return ToolResult::success([
                    'entries' => [],
                    'count' => 0,
                    'message' => 'Die Log-Datei ist leer.',
                    'file' => $logPath,
                ]);
            }

            // 6. Zeilen zu Log-Einträgen zusammenfassen (Stacktrace-Parsing)
            $entries = $this->parseLogEntries($rawLines);

            // 7. Filter anwenden
            $filtered = $this->applyFilters($entries, $level, $search, $sinceTime, $untilTime);

            // 8. Auf gewünschte Anzahl beschränken (letzte N Einträge)
            if (count($filtered) > $requestedLines) {
                $filtered = array_slice($filtered, -$requestedLines);
            }

            // 9. Sensible Daten maskieren
            $filtered = $this->maskSensitiveData($filtered);

            // 10. Output formatieren und auf max_chars beschränken
            $output = $this->formatOutput($filtered, $maxChars);

            // 11. Metadaten zusammenstellen
            $fileSize = filesize($logPath);
            $fileSizeFormatted = $this->formatFileSize($fileSize);

            $meta = [
                'file' => $logPath,
                'file_size' => $fileSizeFormatted,
                'total_entries_parsed' => count($entries),
                'entries_after_filter' => count($filtered),
                'entries_returned' => $output['count'],
                'truncated' => $output['truncated'],
            ];

            if ($level) {
                $meta['filter_level'] = $level;
            }
            if ($search) {
                $meta['filter_search'] = $search;
            }
            if ($since) {
                $meta['filter_since'] = $since;
            }
            if ($until) {
                $meta['filter_until'] = $until;
            }

            return ToolResult::success([
                'entries' => $output['entries'],
                'count' => $output['count'],
                'meta' => $meta,
            ]);

        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Lesen der Log-Datei: ' . $e->getMessage());
        }
    }

    /**
     * Prüft ob der aktuelle User Zugriff auf das Log-Tool hat
     */
    private function checkAccess(ToolContext $context): ?ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTHENTICATION_REQUIRED', 'Kein User im Kontext gefunden.');
        }

        $allowedUsersEnv = env('LOG_TOOL_ALLOWED_USERS', '21');
        $allowedIds = array_map('intval', array_filter(
            array_map('trim', explode(',', $allowedUsersEnv)),
            fn($v) => $v !== ''
        ));

        $userId = (int) $context->user->id;

        if (!in_array($userId, $allowedIds, true)) {
            return ToolResult::error(
                'ACCESS_DENIED',
                'Du hast keinen Zugriff auf das Log-Tool. Zugriff ist auf bestimmte User beschränkt (LOG_TOOL_ALLOWED_USERS).'
            );
        }

        return null;
    }

    /**
     * Ermittelt den Pfad zur laravel.log
     */
    private function resolveLogPath(): ?string
    {
        // Standard: storage/logs/laravel.log
        $path = storage_path('logs/laravel.log');
        if (file_exists($path) && is_readable($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Liest die letzten N Zeilen einer Datei (effizient, ohne ganze Datei in Speicher)
     *
     * @return string[]
     */
    private function tailFile(string $path, int $maxLines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        if ($totalLines === 0) {
            return [];
        }

        $startLine = max(0, $totalLines - $maxLines);
        $lines = [];

        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->current();
            if ($line !== false && $line !== '') {
                $lines[] = rtrim($line, "\r\n");
            }
            $file->next();
        }

        return $lines;
    }

    /**
     * Parst rohe Log-Zeilen zu strukturierten Einträgen
     *
     * Laravel-Log-Format:
     * [2025-01-15 14:30:00] production.ERROR: Something went wrong {"context":"data"}
     * #0 /path/to/file.php(123): Class->method()
     * #1 /path/to/file.php(456): Class->method()
     *
     * Multiline-Einträge (Exception + Stacktrace) werden als ein Block zusammengefasst.
     *
     * @param string[] $lines
     * @return array[]
     */
    private function parseLogEntries(array $lines): array
    {
        $entries = [];
        $currentEntry = null;

        // Laravel Log-Zeile Regex: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        $logLinePattern = '/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+\w+\.(\w+):\s+(.*)/s';

        foreach ($lines as $line) {
            if (preg_match($logLinePattern, $line, $matches)) {
                // Neue Log-Zeile gefunden → vorherigen Eintrag speichern
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }

                $currentEntry = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'message' => $matches[3],
                    'stacktrace' => '',
                ];
            } else {
                // Fortsetzungszeile (Stacktrace oder Multiline-Message)
                if ($currentEntry !== null) {
                    $currentEntry['stacktrace'] .= ($currentEntry['stacktrace'] !== '' ? "\n" : '') . $line;
                }
                // Zeilen vor dem ersten Log-Eintrag werden ignoriert
            }
        }

        // Letzten Eintrag speichern
        if ($currentEntry !== null) {
            $entries[] = $currentEntry;
        }

        return $entries;
    }

    /**
     * Wendet Filter auf geparste Log-Einträge an
     *
     * @param array[] $entries
     * @return array[]
     */
    private function applyFilters(array $entries, ?string $level, ?string $search, ?\DateTimeImmutable $since, ?\DateTimeImmutable $until): array
    {
        return array_values(array_filter($entries, function (array $entry) use ($level, $search, $since, $until) {
            // Level-Filter
            if ($level !== null && $entry['level'] !== strtolower($level)) {
                return false;
            }

            // Zeitraum-Filter
            if ($since !== null || $until !== null) {
                $entryTime = $this->parseTimestamp($entry['timestamp']);
                if ($entryTime === null) {
                    return false; // Kann Zeitstempel nicht parsen → ausschließen
                }

                if ($since !== null && $entryTime < $since) {
                    return false;
                }
                if ($until !== null && $entryTime > $until) {
                    return false;
                }
            }

            // Search/Grep-Filter (durchsucht Message + Stacktrace)
            if ($search !== null) {
                $fullText = $entry['message'] . "\n" . $entry['stacktrace'];
                if (stripos($fullText, $search) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Parst einen Zeitstempel-String zu DateTimeImmutable
     */
    private function parseTimestamp(string $timestamp): ?\DateTimeImmutable
    {
        // Format: YYYY-MM-DD HH:MM:SS
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $timestamp);
        if ($dt !== false) {
            return $dt;
        }

        // Format: YYYY-MM-DD (setzt 00:00:00)
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $timestamp);
        if ($dt !== false) {
            return $dt->setTime(0, 0, 0);
        }

        return null;
    }

    /**
     * Maskiert sensible Daten in Log-Einträgen
     *
     * @param array[] $entries
     * @return array[]
     */
    private function maskSensitiveData(array $entries): array
    {
        return array_map(function (array $entry) {
            $entry['message'] = $this->maskString($entry['message']);
            if ($entry['stacktrace'] !== '') {
                $entry['stacktrace'] = $this->maskString($entry['stacktrace']);
            }
            return $entry;
        }, $entries);
    }

    /**
     * Maskiert sensible Daten in einem String
     */
    private function maskString(string $text): string
    {
        foreach (self::SENSITIVE_PATTERNS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }
        return $text;
    }

    /**
     * Formatiert Einträge für die Ausgabe und beschränkt auf max_chars
     *
     * @param array[] $entries
     * @return array{entries: string[], count: int, truncated: bool}
     */
    private function formatOutput(array $entries, int $maxChars): array
    {
        $output = [];
        $totalChars = 0;
        $truncated = false;

        // Von hinten nach vorne (neueste zuerst), aber Reihenfolge beibehalten
        foreach ($entries as $entry) {
            $formatted = $this->formatEntry($entry);
            $entryLength = mb_strlen($formatted);

            if ($totalChars + $entryLength > $maxChars) {
                $truncated = true;
                // Versuche den Eintrag gekürzt einzufügen, wenn noch Platz ist
                $remaining = $maxChars - $totalChars;
                if ($remaining > 200) {
                    $output[] = mb_substr($formatted, 0, $remaining - 50) . "\n... [TRUNCATED]";
                    $totalChars += $remaining;
                }
                break;
            }

            $output[] = $formatted;
            $totalChars += $entryLength;
        }

        return [
            'entries' => $output,
            'count' => count($output),
            'truncated' => $truncated,
        ];
    }

    /**
     * Formatiert einen einzelnen Log-Eintrag als lesbaren String
     */
    private function formatEntry(array $entry): string
    {
        $level = strtoupper($entry['level']);
        $line = "[{$entry['timestamp']}] {$level}: {$entry['message']}";

        if ($entry['stacktrace'] !== '') {
            $line .= "\n" . $entry['stacktrace'];
        }

        return $line;
    }

    /**
     * Formatiert Dateigröße menschenlesbar
     */
    private function formatFileSize(int|false $bytes): string
    {
        if ($bytes === false) {
            return 'unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
