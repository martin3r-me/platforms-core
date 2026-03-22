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

        // Find the heading line and insert after it (before the next heading of same or higher level)
        $headingPattern = '/^(#{1,6})\s+' . preg_quote($heading, '/') . '\s*$/m';

        if (preg_match($headingPattern, $existing, $matches, PREG_OFFSET_MATCH)) {
            $headingLevel = strlen($matches[1][0]);
            $insertPos = $matches[0][1] + strlen($matches[0][0]);

            // Find the next heading of same or higher level
            $rest = substr($existing, $insertPos);
            $nextHeadingPattern = '/^#{1,' . $headingLevel . '}\s+/m';

            if (preg_match($nextHeadingPattern, $rest, $nextMatch, PREG_OFFSET_MATCH)) {
                $insertPos += $nextMatch[0][1];
                $newContent = substr($existing, 0, $insertPos) . "\n" . $content . "\n" . substr($existing, $insertPos);
            } else {
                // No next heading — append at end
                $newContent = $existing . "\n" . $content . "\n";
            }
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

        if (! str_starts_with($content, '---')) {
            return null;
        }

        $endPos = strpos($content, "\n---", 3);
        if ($endPos === false) {
            return null;
        }

        $yaml = substr($content, 3, $endPos - 3);

        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse(trim($yaml));

            return is_array($parsed) ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
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
