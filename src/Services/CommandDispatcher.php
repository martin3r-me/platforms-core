<?php

namespace Platform\Core\Services;

use Illuminate\Support\Arr;

class CommandDispatcher
{
    public function dispatch(array $command, array $slots): array
    {
        $handler = $command['handler'] ?? null;
        if (!$handler) {
            return ['ok' => false, 'message' => 'Kein Handler definiert'];
        }

        // Handler-Typen: ['service', 'Class@method'] | ['route', routeName] | ['livewire', event]
        if (is_array($handler) && ($handler[0] ?? null) === 'service') {
            [$type, $callable] = $handler;
            return $this->callService($callable, $slots);
        }
        if (is_array($handler) && ($handler[0] ?? null) === 'route') {
            [$type, $routeName] = $handler;
            $url = route($routeName, Arr::only($slots, ['id', 'name']));
            return ['ok' => true, 'navigate' => $url, 'message' => 'Navigation bereit'];
        }
        if (is_array($handler) && ($handler[0] ?? null) === 'livewire') {
            [$type, $event] = $handler;
            return ['ok' => true, 'dispatch' => $event, 'payload' => $slots];
        }

        // Fallback callable
        if (is_callable($handler)) {
            $res = call_user_func($handler, $slots);
            return ['ok' => true, 'data' => $res];
        }

        return ['ok' => false, 'message' => 'Unbekannter Handler-Typ'];
    }

    protected function callService(string $callable, array $slots): array
    {
        if (str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);
            $svc = app($class);
            $res = $svc->{$method}($slots);
            return ['ok' => true, 'data' => $res];
        }
        return ['ok' => false, 'message' => 'Service-Callable ungültig'];
    }
}


