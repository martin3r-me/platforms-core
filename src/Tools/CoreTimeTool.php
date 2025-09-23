<?php

namespace Platform\Core\Tools;

class CoreTimeTool
{
    public function getNow(array $slots = []): array
    {
        $now = now();
        return [
            'ok' => true,
            'data' => [
                'iso' => $now->toIso8601String(),
                'date' => $now->toDateString(),
                'time' => $now->format('H:i:s'),
                'timezone' => config('app.timezone'),
                'unix' => $now->timestamp,
            ],
            'message' => 'Aktuelle Serverzeit',
        ];
    }
}


