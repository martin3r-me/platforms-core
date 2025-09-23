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
            $res = $this->callService($callable, $slots);
            // Service gibt ein Array zurück und kann ok/navigate/message/data setzen.
            if (is_array($res)) {
                return [
                    'ok' => (bool)($res['ok'] ?? true),
                    'navigate' => $res['navigate'] ?? null,
                    'message' => $res['message'] ?? null,
                    'data' => $res['data'] ?? null,
                ];
            }
            // Fallback: beliebige Rückgabe als data verpacken
            return ['ok' => true, 'data' => $res];
        }
        if (is_array($handler) && ($handler[0] ?? null) === 'route') {
            // Varianten:
            // ['route', 'route.name']
            // ['route', 'route.name', ['paramName' => 'slot_key', ...]]
            $type = $handler[0];
            $routeName = $handler[1] ?? null;
            $paramMap = $handler[2] ?? null;
            if (!$routeName) {
                return ['ok' => false, 'message' => 'Route-Name fehlt'];
            }
            $params = [];
            if (is_array($paramMap)) {
                foreach ($paramMap as $paramName => $slotKey) {
                    if (array_key_exists($slotKey, $slots)) {
                        $params[$paramName] = $slots[$slotKey];
                    }
                }
            } else {
                $params = Arr::only($slots, ['id', 'name']);
            }
            // Wenn erforderliche Parameter fehlen, nicht crashen, sondern Resolver erlauben
            try {
                $url = route($routeName, $params);
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Route-Parameter unvollständig',
                    'needResolve' => true,
                ];
            }
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
            // Erwartet: Array mit optionalen Keys ok/navigate/message/data
            return $svc->{$method}($slots);
        }
        return ['ok' => false, 'message' => 'Service-Callable ungültig'];
    }
}


