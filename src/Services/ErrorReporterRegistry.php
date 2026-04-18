<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorReporterRegistry
{
    /** @var array<string, array{namespace: string, endpoint: string}> */
    private array $reporters = [];

    /**
     * Register a package for error reporting.
     * Reads DEV_ERROR_ENDPOINT_{KEY} from ENV. If not set, skips silently.
     *
     * @param string $key Package key (e.g. 'planner', 'sheets', 'wiki')
     * @param string $namespace Root namespace to match (e.g. 'Platform\\Planner')
     */
    public function register(string $key, string $namespace): void
    {
        $envKey = 'DEV_ERROR_ENDPOINT_' . strtoupper(str_replace('-', '_', $key));

        // Use getenv() instead of env() to work with cached config
        $endpoint = getenv($envKey) ?: env($envKey);

        if (!$endpoint) {
            return;
        }

        $this->reporters[$key] = [
            'namespace' => $namespace,
            'endpoint' => $endpoint,
        ];

        Log::debug('[ErrorReporterRegistry] Registered', ['key' => $key, 'namespace' => $namespace]);
    }

    /**
     * Report a throwable to all matching package endpoints.
     */
    public function report(Throwable $e, array $context = []): void
    {
        if (empty($this->reporters)) {
            return;
        }

        $exceptionClass = get_class($e);
        $file = $e->getFile();

        Log::debug('[ErrorReporterRegistry] Reporting', [
            'exception' => $exceptionClass,
            'file' => $file,
            'reporters' => array_keys($this->reporters),
        ]);

        $matched = false;

        foreach ($this->reporters as $key => $config) {
            if (!$this->matches($exceptionClass, $file, $config['namespace'])) {
                continue;
            }

            $matched = true;
            Log::info('[ErrorReporterRegistry] Matched', ['key' => $key, 'exception' => $exceptionClass]);
            $this->send($key, $config['endpoint'], $e, $context);
        }

        if (!$matched) {
            Log::debug('[ErrorReporterRegistry] No match', [
                'exception' => $exceptionClass,
                'file' => $file,
            ]);
        }
    }

    /**
     * Check if an exception belongs to a package by namespace or file path.
     */
    protected function matches(string $exceptionClass, string $file, string $namespace): bool
    {
        // Match by exception class namespace
        if (str_starts_with($exceptionClass, $namespace . '\\')) {
            return true;
        }

        // Match by file path - derive module directory from namespace
        // Platform\Sheets -> modules/sheets/, Platform\ProjectCanvas -> modules/project-canvas/
        $parts = explode('\\', $namespace);
        $moduleName = end($parts);

        // Convert PascalCase to kebab-case for directory matching
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));

        if (str_contains($file, '/modules/' . $kebab . '/') ||
            str_contains($file, '/modules/' . strtolower($moduleName) . '/')) {
            return true;
        }

        // Also match services directory pattern
        if (str_contains($file, '/services/' . $kebab . '/') ||
            str_contains($file, '/services/' . strtolower($moduleName) . '/')) {
            return true;
        }

        // Match vendor path for deployed composer packages
        // vendor/martin3r/platform-{kebab}/ or vendor/martin3r/platforms-{kebab}/
        if (str_contains($file, '/vendor/martin3r/platform-' . $kebab . '/') ||
            str_contains($file, '/vendor/martin3r/platforms-' . $kebab . '/') ||
            str_contains($file, '/vendor/martin3r/platform-' . strtolower($moduleName) . '/') ||
            str_contains($file, '/vendor/martin3r/platforms-' . strtolower($moduleName) . '/')) {
            return true;
        }

        return false;
    }

    /**
     * Send error payload to the ingest endpoint.
     */
    protected function send(string $key, string $endpoint, Throwable $e, array $context): void
    {
        try {
            $payload = [
                'package_key' => $key,
                'exception_class' => get_class($e),
                'message' => mb_substr($e->getMessage(), 0, 2000),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'http_code' => $context['http_code'] ?? null,
                'is_console' => $context['is_console'] ?? app()->runningInConsole(),
                'url' => $context['url'] ?? (app()->runningInConsole() ? null : request()->fullUrl()),
                'method' => $context['method'] ?? (app()->runningInConsole() ? null : request()->method()),
                'user_id' => $context['user_id'] ?? auth()->id(),
                'instance' => config('app.url'),
                'instance_name' => config('app.name'),
                'timestamp' => now()->toIso8601String(),
                'stack_trace' => array_slice(
                    array_map(fn ($frame) => [
                        'file' => $frame['file'] ?? null,
                        'line' => $frame['line'] ?? null,
                        'function' => $frame['function'] ?? null,
                        'class' => $frame['class'] ?? null,
                    ], $e->getTrace()),
                    0,
                    30
                ),
            ];

            if (isset($context['extra'])) {
                $payload['extra'] = $context['extra'];
            }

            $response = Http::timeout(5)
                ->retry(0)
                ->post($endpoint, $payload);

            Log::info('[ErrorReporterRegistry] Sent', [
                'key' => $key,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (Throwable $sendError) {
            Log::warning('[ErrorReporterRegistry] Failed to send error report', [
                'package' => $key,
                'error' => $sendError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if any reporters are registered.
     */
    public function hasReporters(): bool
    {
        return !empty($this->reporters);
    }

    /**
     * Get all registered reporter keys.
     */
    public function registeredKeys(): array
    {
        return array_keys($this->reporters);
    }
}
