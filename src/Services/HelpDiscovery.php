<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Platform\Core\Registry\ModuleRegistry;
use Symfony\Component\Yaml\Yaml;

class HelpDiscovery
{
    /**
     * Get the full navigation tree (cached).
     */
    public static function getTree(): array
    {
        return Cache::remember('help:tree', 3600, function () {
            return static::buildTree();
        });
    }

    /**
     * Get the tree filtered by user's accessible modules.
     */
    public static function getTreeForUser(): array
    {
        $tree = static::getTree();
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $team = $user->currentTeamRelation;
        if (!$team) {
            return [];
        }

        $rootTeam = $team->getRootTeam();
        $teamId = $team->id;
        $rootTeamId = $rootTeam->id;

        return collect($tree)->filter(function ($module) use ($user, $team, $rootTeam, $teamId, $rootTeamId) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();
            if (!$moduleModel) {
                return false;
            }

            if ($moduleModel->isRootScoped()) {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $rootTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $rootTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            } else {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $teamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $team->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            }

            return $userAllowed || $teamAllowed;
        })->values()->toArray();
    }

    /**
     * Get a rendered page.
     *
     * @return array{html: string, title: string, breadcrumb: array}
     */
    public static function getPage(string $module, string $path = 'index'): array
    {
        $hash = md5("{$module}:{$path}");

        return Cache::remember("help:page:{$hash}", 3600, function () use ($module, $path) {
            return static::renderPage($module, $path);
        });
    }

    /**
     * Clear all help caches.
     */
    public static function clearCache(): void
    {
        Cache::forget('help:tree');

        // Clear page caches for all registered modules
        foreach (static::getDocsDirectories() as $moduleKey => $docsDir) {
            foreach (static::findMarkdownFiles($docsDir) as $file) {
                $relativePath = static::getRelativePath($docsDir, $file);
                $hash = md5("{$moduleKey}:{$relativePath}");
                Cache::forget("help:page:{$hash}");
            }
        }
    }

    /**
     * Resolve docs directories for all registered modules.
     * Uses the ServiceProvider class location to find sibling docs/ folders.
     *
     * @return array<string, string> moduleKey => docsDir path
     */
    protected static function getDocsDirectories(): array
    {
        $dirs = [];

        foreach (ModuleRegistry::all() as $key => $config) {
            // Strategy 1: Explicit docs_path in module config
            if (!empty($config['docs_path']) && File::isDirectory($config['docs_path'])) {
                $dirs[$key] = $config['docs_path'];
                continue;
            }

            // Strategy 2: Resolve via ServiceProvider class location
            $providerClass = $config['provider'] ?? null;
            if (!$providerClass) {
                // Guess the provider class name from the module key
                $studlyKey = Str::studly($key);
                $providerClass = "Platform\\{$studlyKey}\\{$studlyKey}ServiceProvider";
            }

            if (class_exists($providerClass)) {
                try {
                    $reflection = new \ReflectionClass($providerClass);
                    $providerDir = dirname($reflection->getFileName());
                    // ServiceProvider sits in src/, docs/ is a sibling
                    $docsDir = dirname($providerDir) . '/docs';
                    if (File::isDirectory($docsDir)) {
                        $dirs[$key] = $docsDir;
                    }
                } catch (\Throwable) {
                    // Skip if reflection fails
                }
            }
        }

        return $dirs;
    }

    /**
     * Resolve the docs directory for a single module.
     */
    protected static function getDocsDir(string $moduleKey): ?string
    {
        $dirs = static::getDocsDirectories();
        return $dirs[$moduleKey] ?? null;
    }

    /**
     * Build the complete navigation tree from registered modules.
     */
    protected static function buildTree(): array
    {
        $tree = [];

        foreach (static::getDocsDirectories() as $moduleKey => $docsDir) {
            $moduleMeta = static::readMeta($docsDir);

            // Fall back to registered module title/icon
            $registeredModule = ModuleRegistry::get($moduleKey);
            $fallbackTitle = $registeredModule['title'] ?? Str::title(str_replace('-', ' ', $moduleKey));
            $fallbackIcon = $registeredModule['navigation']['icon'] ?? null;

            $moduleNode = [
                'key' => $moduleKey,
                'title' => $moduleMeta['title'] ?? $fallbackTitle,
                'icon' => $moduleMeta['icon'] ?? $fallbackIcon,
                'order' => $moduleMeta['order'] ?? ($registeredModule['navigation']['order'] ?? 99),
                'sections' => static::buildSections($docsDir, $moduleKey),
                'has_index' => File::exists($docsDir . '/index.md'),
            ];

            $tree[] = $moduleNode;
        }

        usort($tree, function ($a, $b) {
            if ($a['order'] !== $b['order']) {
                return $a['order'] <=> $b['order'];
            }
            return strcasecmp($a['title'], $b['title']);
        });

        return $tree;
    }

    /**
     * Build sections (subdirectories) + standalone pages for a module's docs.
     */
    protected static function buildSections(string $docsDir, string $moduleKey): array
    {
        $sections = [];

        // Collect standalone pages (non-index .md files at root level)
        foreach (File::files($docsDir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $filename = $file->getFilenameWithoutExtension();
            if ($filename === 'index') {
                continue;
            }

            $frontmatter = static::parseFrontmatter(File::get($file->getPathname()));
            $sections[] = [
                'type' => 'page',
                'key' => $filename,
                'path' => $filename,
                'title' => $frontmatter['title'] ?? Str::title(str_replace('-', ' ', $filename)),
                'order' => $frontmatter['order'] ?? 99,
            ];
        }

        // Collect subdirectories as section groups
        foreach (File::directories($docsDir) as $subDir) {
            $sectionKey = basename($subDir);
            $sectionMeta = static::readMeta($subDir);

            $pages = [];
            foreach (File::files($subDir) as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }
                $filename = $file->getFilenameWithoutExtension();
                $frontmatter = static::parseFrontmatter(File::get($file->getPathname()));

                $pages[] = [
                    'key' => $filename,
                    'path' => $sectionKey . '/' . $filename,
                    'title' => $frontmatter['title'] ?? Str::title(str_replace('-', ' ', $filename)),
                    'order' => $frontmatter['order'] ?? ($filename === 'index' ? 0 : 99),
                ];
            }

            usort($pages, fn ($a, $b) => $a['order'] <=> $b['order'] ?: strcasecmp($a['title'], $b['title']));

            $sections[] = [
                'type' => 'group',
                'key' => $sectionKey,
                'title' => $sectionMeta['title'] ?? Str::title(str_replace('-', ' ', $sectionKey)),
                'order' => $sectionMeta['order'] ?? 99,
                'pages' => $pages,
            ];
        }

        usort($sections, fn ($a, $b) => $a['order'] <=> $b['order'] ?: strcasecmp($a['title'], $b['title']));

        return $sections;
    }

    /**
     * Render a single markdown page.
     */
    protected static function renderPage(string $module, string $path): array
    {
        $docsDir = static::getDocsDir($module);

        if (!$docsDir) {
            return [
                'html' => '<p class="text-[var(--ui-muted)]">Diese Seite wurde nicht gefunden.</p>',
                'title' => 'Nicht gefunden',
                'breadcrumb' => [['label' => $module, 'path' => null]],
            ];
        }

        // Resolve file path
        $filePath = $docsDir . '/' . $path;
        if (!Str::endsWith($filePath, '.md')) {
            $filePath .= '.md';
        }

        if (!File::exists($filePath)) {
            return [
                'html' => '<p class="text-[var(--ui-muted)]">Diese Seite wurde nicht gefunden.</p>',
                'title' => 'Nicht gefunden',
                'breadcrumb' => [['label' => $module, 'path' => null]],
            ];
        }

        $raw = File::get($filePath);
        $frontmatter = static::parseFrontmatter($raw);
        $content = static::stripFrontmatter($raw);

        // Rewrite internal links: [Text](other-page.md) → wire:click links
        $content = static::rewriteLinks($content, $module);

        $html = Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Build breadcrumb
        $breadcrumb = [['label' => static::getModuleTitle($module), 'path' => 'index']];
        $parts = explode('/', $path);
        if (count($parts) > 1) {
            $sectionKey = $parts[0];
            $sectionMeta = static::readMeta($docsDir . '/' . $sectionKey);
            $breadcrumb[] = [
                'label' => $sectionMeta['title'] ?? Str::title(str_replace('-', ' ', $sectionKey)),
                'path' => $sectionKey . '/index',
            ];
        }

        $title = $frontmatter['title']
            ?? Str::title(str_replace('-', ' ', basename($path, '.md')));

        return [
            'html' => $html,
            'title' => $title,
            'breadcrumb' => $breadcrumb,
        ];
    }

    /**
     * Rewrite internal markdown links to wire:click dispatches.
     */
    protected static function rewriteLinks(string $content, string $module): string
    {
        return preg_replace_callback(
            '/\[([^\]]+)\]\((?!https?:\/\/)([^)]+?)(?:\.md)?\)/',
            function ($matches) use ($module) {
                $text = $matches[1];
                $path = $matches[2];
                $path = ltrim($path, './');
                return "<a href=\"#\" onclick=\"\$dispatch('open-help-page', { module: '{$module}', path: '{$path}' }); return false;\" class=\"help-internal-link\">{$text}</a>";
            },
            $content
        );
    }

    /**
     * Read _meta.yaml from a directory.
     */
    protected static function readMeta(string $dir): array
    {
        $metaFile = $dir . '/_meta.yaml';
        if (!File::exists($metaFile)) {
            return [];
        }

        try {
            return Yaml::parseFile($metaFile) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Parse YAML frontmatter from markdown content.
     */
    protected static function parseFrontmatter(string $content): array
    {
        if (!Str::startsWith(trim($content), '---')) {
            return [];
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);
        if (count($parts) < 3) {
            return [];
        }

        try {
            return Yaml::parse($parts[1]) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Strip YAML frontmatter from markdown content.
     */
    protected static function stripFrontmatter(string $content): string
    {
        if (!Str::startsWith(trim($content), '---')) {
            return $content;
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);
        if (count($parts) < 3) {
            return $content;
        }

        return trim($parts[2]);
    }

    /**
     * Get the display title for a module.
     */
    protected static function getModuleTitle(string $moduleKey): string
    {
        $docsDir = static::getDocsDir($moduleKey);
        if ($docsDir) {
            $meta = static::readMeta($docsDir);
            if (!empty($meta['title'])) {
                return $meta['title'];
            }
        }

        $registered = ModuleRegistry::get($moduleKey);
        return $registered['title'] ?? Str::title(str_replace('-', ' ', $moduleKey));
    }

    /**
     * Find all .md files recursively.
     */
    protected static function findMarkdownFiles(string $dir): array
    {
        $files = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * Get relative path from docs dir, without .md extension.
     */
    protected static function getRelativePath(string $docsDir, string $filePath): string
    {
        $relative = Str::after($filePath, $docsDir . '/');
        return Str::beforeLast($relative, '.md');
    }
}
