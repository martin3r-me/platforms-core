<?php

namespace Platform\Core\Support;

/**
 * Prueft einen hochgeladenen Datei-Upload gegen die pro-Feld konfigurierbaren
 * Beschraenkungen eines `file`-Extra-Fields.
 *
 * Reine Logik (kein Framework/DB) — damit unit-testbar und im Public-Form-
 * Upload-Handler wie auch anderswo wiederverwendbar.
 *
 * Konfiguration liegt in den Feld-`options`:
 *   - accept:       Liste erlaubter Endungen ohne Punkt, z.B. ["jpg","png","pdf"].
 *                   Leer/fehlt → keine Format-Beschraenkung (Default, unveraendert).
 *   - max_size_mb:  Maximale Dateigroesse in MB. Fehlt → keine Groessen-Beschraenkung.
 *
 * Rueckgabe: null wenn ok, sonst eine fertige deutsche Fehlermeldung (dient
 * zugleich den definierten Fehlertexten am Feld).
 */
class FileUploadValidator
{
    private const BYTES_PER_MB = 1048576;

    /** Endungs-Aliase, damit z.B. .jpeg akzeptiert wird wenn "jpg" erlaubt ist. */
    private const EXTENSION_ALIASES = [
        'jpeg' => 'jpg',
        'jpg'  => 'jpg',
        'tif'  => 'tiff',
        'tiff' => 'tiff',
        'htm'  => 'html',
        'html' => 'html',
    ];

    public static function validate(?string $extension, ?string $mimeType, int $sizeBytes, array $options): ?string
    {
        $accept = $options['accept'] ?? [];
        $maxSizeMb = $options['max_size_mb'] ?? null;

        // 1) Format zuerst (grundsaetzlicher als Groesse).
        if (is_array($accept) && count($accept) > 0) {
            $allowed = self::normalizeList($accept);
            $ext = self::canonicalize((string) $extension);

            if ($ext === '' || !in_array($ext, $allowed, true)) {
                return sprintf(
                    'Ungültiges Dateiformat%s. Erlaubt sind: %s.',
                    $extension ? ' „' . strtolower((string) $extension) . '"' : '',
                    self::humanList($accept),
                );
            }
        }

        // 2) Groesse.
        if ($maxSizeMb !== null && $maxSizeMb > 0) {
            $maxBytes = (int) round($maxSizeMb * self::BYTES_PER_MB);
            if ($sizeBytes > $maxBytes) {
                return sprintf(
                    'Die Datei ist zu groß (%s MB). Maximal erlaubt sind %s MB.',
                    self::formatMb($sizeBytes),
                    rtrim(rtrim(number_format((float) $maxSizeMb, 1, ',', ''), '0'), ','),
                );
            }
        }

        return null;
    }

    /** Endungen normalisieren + auf kanonische Form (Aliase) abbilden. */
    private static function normalizeList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            $out[] = self::canonicalize((string) $item);
        }
        return array_values(array_unique(array_filter($out, fn ($e) => $e !== '')));
    }

    private static function canonicalize(string $ext): string
    {
        $ext = strtolower(ltrim(trim($ext), '.'));
        return self::EXTENSION_ALIASES[$ext] ?? $ext;
    }

    /** Anzeigeliste fuer die Fehlermeldung, z.B. "JPG, PNG, PDF". */
    private static function humanList(array $accept): string
    {
        $labels = [];
        foreach ($accept as $item) {
            $labels[] = strtoupper(ltrim(trim((string) $item), '.'));
        }
        return implode(', ', array_values(array_unique(array_filter($labels))));
    }

    private static function formatMb(int $bytes): string
    {
        $mb = $bytes / self::BYTES_PER_MB;
        return number_format($mb, 1, ',', '');
    }
}
