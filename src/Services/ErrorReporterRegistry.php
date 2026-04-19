<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorReporterRegistry
{
    /** @var array<string, string> key => namespace */
    private array $namespaces = [];

    private ?string $endpoint = null;

    private bool $endpointResolved = false;

    /**
     * Register a package namespace for error identification.
     *
     * @param string $key Package key (e.g. 'organization', 'planner')
     * @param string $namespace Root namespace to match (e.g. 'Platform\\Organization')
     */
    public function register(string $key, string $namespace): void
    {
        $this->namespaces[$key] = $namespace;
    }

    /**
     * Resolve the single endpoint from ENV (lazy, once).
     */
    protected function resolveEndpoint(): ?string
    {
        if (!$this->endpointResolved) {
            $this->endpoint = getenv('DEV_ERROR_ENDPOINT') ?: env('DEV_ERROR_ENDPOINT') ?: null;
            $this->endpointResolved = true;

            if ($this->endpoint) {
                Log::debug('[ErrorReporter] Endpoint configured', [
                    'endpoint' => substr($this->endpoint, 0, 60) . '...',
                    'packages' => array_keys($this->namespaces),
                ]);
            }
        }

        return $this->endpoint;
    }

    /**
     * Identify which package an exception belongs to.
     */
    public function identifyPackage(Throwable $e): ?string
    {
        $exceptionClass = get_class($e);
        $file = $e->getFile();

        foreach ($this->namespaces as $key => $namespace) {
            if ($this->matches($exceptionClass, $file, $namespace)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Report a throwable to the central error endpoint.
     */
    public function report(Throwable $e, array $context = []): void
    {
        if (empty($this->namespaces)) {
            return;
        }

        $endpoint = $this->resolveEndpoint();
        if (!$endpoint) {
            return;
        }

        $packageKey = $this->identifyPackage($e);
        if (!$packageKey) {
            return;
        }

        $this->send($packageKey, $endpoint, $e, $context);
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

        // Derive module directory from namespace
        $parts = explode('\\', $namespace);
        $moduleName = end($parts);
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));
        $lower = strtolower($moduleName);

        // Match module paths
        if (str_contains($file, '/modules/' . $kebab . '/') ||
            str_contains($file, '/modules/' . $lower . '/')) {
            return true;
        }

        // Match vendor paths (deployed composer packages)
        if (str_contains($file, '/vendor/martin3r/platform-' . $kebab . '/') ||
            str_contains($file, '/vendor/martin3r/platforms-' . $kebab . '/') ||
            str_contains($file, '/vendor/martin3r/platform-' . $lower . '/') ||
            str_contains($file, '/vendor/martin3r/platforms-' . $lower . '/')) {
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

            Log::info('[ErrorReporter] Sent', [
                'key' => $key,
                'status' => $response->status(),
            ]);
        } catch (Throwable $sendError) {
            Log::warning('[ErrorReporter] Send failed', [
                'package' => $key,
                'error' => $sendError->getMessage(),
            ]);
        }
    }

    /**
     * Check if any namespaces are registered.
     */
    public function hasReporters(): bool
    {
        return !empty($this->namespaces);
    }

    /**
     * Get all registered package keys.
     */
    public function registeredKeys(): array
    {
        return array_keys($this->namespaces);
    }

    /**
     * Get the configured endpoint (for debugging).
     */
    public function getEndpoint(): ?string
    {
        return $this->resolveEndpoint();
    }
}
