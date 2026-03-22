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
