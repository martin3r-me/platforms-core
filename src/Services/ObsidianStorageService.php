<?php

namespace Platform\Core\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Platform\Core\Models\ObsidianVault;

class ObsidianStorageService
{
    /**
     * Build an on-the-fly S3-compatible filesystem for the given vault.
     */
    public function disk(ObsidianVault $vault): Filesystem
    {
        $config = [
            'driver' => 's3',
            'key' => $vault->access_key,
            'secret' => $vault->secret_key,
            'region' => $vault->region ?? 'us-east-1',
            'bucket' => $vault->bucket,
            'options' => [
                'connect_timeout' => 5,
                'timeout' => 10,
            ],
        ];

        if ($vault->endpoint) {
            $config['endpoint'] = $vault->endpoint;
            $config['use_path_style_endpoint'] = true;
        }

        return Storage::build($config);
    }

    public function listFiles(ObsidianVault $vault, string $path = '/'): array
    {
        $path = $this->resolvePath($vault, $path);
        $disk = $this->disk($vault);

        $files = collect($disk->files($path))->map(fn (string $f) => [
            'path' => $this->stripPrefix($vault, $f),
            'type' => 'file',
            'size' => $disk->size($f),
            'last_modified' => $disk->lastModified($f),
        ])->all();

        $directories = collect($disk->directories($path))->map(fn (string $d) => [
            'path' => $this->stripPrefix($vault, $d),
            'type' => 'directory',
        ])->all();

        return array_merge($directories, $files);
    }

    public function readFile(ObsidianVault $vault, string $path): string
    {
        $path = $this->resolvePath($vault, $path);

        return $this->disk($vault)->get($path);
    }

    public function writeFile(ObsidianVault $vault, string $path, string $content): bool
    {
        $path = $this->resolvePath($vault, $path);

        return $this->disk($vault)->put($path, $content);
    }

    public function deleteFile(ObsidianVault $vault, string $path): bool
    {
        $path = $this->resolvePath($vault, $path);

        return $this->disk($vault)->delete($path);
    }

    public function moveFile(ObsidianVault $vault, string $from, string $to): bool
    {
        $from = $this->resolvePath($vault, $from);
        $to = $this->resolvePath($vault, $to);

        return $this->disk($vault)->move($from, $to);
    }

    public function createFolder(ObsidianVault $vault, string $path): bool
    {
        $path = $this->resolvePath($vault, $path);

        return $this->disk($vault)->makeDirectory($path);
    }

    public function deleteFolder(ObsidianVault $vault, string $path): bool
    {
        $path = $this->resolvePath($vault, $path);

        return $this->disk($vault)->deleteDirectory($path);
    }

    public function exists(ObsidianVault $vault, string $path): bool
    {
        $path = $this->resolvePath($vault, $path);

        return $this->disk($vault)->exists($path);
    }

    /**
     * List files recursively (all levels).
     */
    public function listFilesRecursive(ObsidianVault $vault, string $path = '/'): array
    {
        $path = $this->resolvePath($vault, $path);
        $disk = $this->disk($vault);

        $files = collect($disk->allFiles($path))->map(fn (string $f) => [
            'path' => $this->stripPrefix($vault, $f),
            'type' => 'file',
            'size' => $disk->size($f),
            'last_modified' => $disk->lastModified($f),
        ])->all();

        $directories = collect($disk->allDirectories($path))->map(fn (string $d) => [
            'path' => $this->stripPrefix($vault, $d),
            'type' => 'directory',
        ])->all();

        return array_merge($directories, $files);
    }

    /**
     * Append content to a file (or create it).
     */
    public function appendFile(ObsidianVault $vault, string $path, string $content): string
    {
        $existing = $this->exists($vault, $path)
            ? $this->readFile($vault, $path)
            : '';

        $newContent = $existing . $content;
        $this->writeFile($vault, $path, $newContent);

        return $newContent;
    }

    /**
     * Insert content under a specific markdown heading.
     */
    public function insertUnderHeading(ObsidianVault $vault, string $path, string $heading, string $content): string
    {
        $existing = $this->exists($vault, $path)
            ? $this->readFile($vault, $path)
            : '';

        // Normalize line endings to \n for reliable regex matching
        $existing = str_replace("\r\n", "\n", $existing);
        $existing = str_replace("\r", "\n", $existing);

        // Split into lines for reliable heading search
        $lines = explode("\n", $existing);
        $headingLineIndex = null;
        $headingLevel = null;
        $quotedHeading = preg_quote($heading, '/');

        foreach ($lines as $i => $line) {
            if (preg_match('/^(#{1,6})\s+' . $quotedHeading . '\s*$/', $line, $m)) {
                $headingLineIndex = $i;
                $headingLevel = strlen($m[1]);
                break;
            }
        }

        if ($headingLineIndex !== null) {
            // Find next heading of same or higher level
            $insertBeforeIndex = null;
            for ($i = $headingLineIndex + 1; $i < count($lines); $i++) {
                if (preg_match('/^#{1,' . $headingLevel . '}\s+/', $lines[$i])) {
                    $insertBeforeIndex = $i;
                    break;
                }
            }

            if ($insertBeforeIndex !== null) {
                // Insert before the next heading
                array_splice($lines, $insertBeforeIndex, 0, [$content]);
            } else {
                // No next heading — append content after last line
                $lines[] = $content;
            }

            $newContent = implode("\n", $lines);
        } else {
            // Heading not found — append at end with heading
            $newContent = $existing . "\n## " . $heading . "\n" . $content . "\n";
        }

        $this->writeFile($vault, $path, $newContent);

        return $newContent;
    }

    /**
     * Search files by name pattern and/or content.
     */
    public function search(ObsidianVault $vault, ?string $query = null, ?string $namePattern = null, string $path = '/'): array
    {
        $items = $this->listFilesRecursive($vault, $path);
        $disk = $this->disk($vault);
        $results = [];

        foreach ($items as $item) {
            if ($item['type'] !== 'file') {
                continue;
            }

            $filePath = $item['path'];

            // Filter by name pattern (glob-like)
            if ($namePattern !== null) {
                if (! fnmatch($namePattern, basename($filePath)) && ! fnmatch($namePattern, $filePath)) {
                    continue;
                }
            }

            // Filter by content search
            if ($query !== null) {
                $resolvedPath = $this->resolvePath($vault, $filePath);
                try {
                    $content = $disk->get($resolvedPath);
                } catch (\Throwable) {
                    continue;
                }

                if (stripos($content, $query) === false) {
                    continue;
                }

                // Extract matching lines for context
                $lines = explode("\n", $content);
                $matchingLines = [];
                foreach ($lines as $lineNum => $line) {
                    if (stripos($line, $query) !== false) {
                        $matchingLines[] = [
                            'line' => $lineNum + 1,
                            'text' => trim($line),
                        ];
                        if (count($matchingLines) >= 5) {
                            break;
                        }
                    }
                }

                $item['matches'] = $matchingLines;
            }

            $results[] = $item;
        }

        return $results;
    }

    /**
     * Parse YAML frontmatter from a markdown file.
     */
    public function parseFrontmatter(ObsidianVault $vault, string $path): ?array
    {
        $content = $this->readFile($vault, $path);

        // Strip BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        $content = ltrim($content);

        if (! str_starts_with($content, "---\n") && ! str_starts_with($content, "---\r")) {
            // Also handle --- as the entire first line (edge case: file is only frontmatter)
            if ($content === '---' || str_starts_with($content, "---\n")) {
                // ok
            } else {
                return null;
            }
        }

        $endPos = strpos($content, "\n---", 3);
        if ($endPos === false) {
            return null;
        }

        // Extract the YAML between the two --- delimiters
        $yaml = substr($content, 4, $endPos - 4); // 4 = strlen("---\n")

        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse(trim($yaml));

            return is_array($parsed) ? $parsed : null;
        } catch (\Throwable) {
            // Fallback: simple key: value parsing for basic frontmatter
            return $this->parseSimpleFrontmatter($yaml);
        }
    }

    /**
     * Fallback parser for simple YAML frontmatter when Symfony YAML is not available.
     */
    private function parseSimpleFrontmatter(string $yaml): ?array
    {
        $result = [];
        $lines = explode("\n", trim($yaml));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            // Handle arrays in [a, b, c] format
            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $value = array_map('trim', explode(',', substr($value, 1, -1)));
            }
            // Handle quoted strings
            elseif ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return ! empty($result) ? $result : null;
    }

    /**
     * Read multiple files at once.
     */
    public function readMultiple(ObsidianVault $vault, array $paths): array
    {
        $results = [];
        $disk = $this->disk($vault);

        foreach ($paths as $path) {
            $resolved = $this->resolvePath($vault, $path);
            try {
                $content = $disk->get($resolved);
                $results[] = [
                    'path' => $path,
                    'content' => $content,
                    'size' => strlen($content),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function testConnection(ObsidianVault $vault): bool
    {
        try {
            $this->disk($vault)->directories($vault->prefix ?? '');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sanitize a user-provided path and prepend the vault prefix.
     */
    private function resolvePath(ObsidianVault $vault, string $path): string
    {
        $path = $this->sanitizePath($path);

        if ($vault->prefix) {
            return rtrim($vault->prefix, '/') . '/' . $path;
        }

        return $path;
    }

    /**
     * Remove the vault prefix from a storage path for display.
     */
    private function stripPrefix(ObsidianVault $vault, string $path): string
    {
        if ($vault->prefix) {
            $prefix = rtrim($vault->prefix, '/') . '/';
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    /**
     * Sanitize a path to prevent traversal attacks.
     *
     * @throws \InvalidArgumentException
     */
    private function sanitizePath(string $path): string
    {
        // Block null bytes
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Invalid path: null bytes are not allowed.');
        }

        // Normalize separators
        $path = str_replace('\\', '/', $path);

        // Strip leading slashes – paths are always relative to the vault prefix
        $path = ltrim($path, '/');

        // Reject path traversal
        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new \InvalidArgumentException('Invalid path: directory traversal is not allowed.');
            }
            $resolved[] = $segment;
        }

        return implode('/', $resolved);
    }
}
