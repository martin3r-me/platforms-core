<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Platform\Core\Registry\CommandRegistry;

class LlmPlanner
{
    public function plan(string $userText): array
    {
        $apiKey = env('OPENAI_API_KEY');
        $base   = rtrim(env('OPENAI_BASE', 'https://api.openai.com/v1'), '/');
        $model  = env('OPENAI_MODEL', 'gpt-4o-mini');
        if (!$apiKey) {
            return ['ok' => false, 'message' => 'OPENAI_API_KEY fehlt'];
        }

        $tools = $this->buildToolsFromRegistry();

        $system = "Du bist ein Assistent in einer Business-Plattform. Nutze bereitgestellte Tools, um Nutzerbefehle auszuführen. Wähle genau EIN passendes Tool mit Parametern. Antworte nicht frei, sondern benutze function-calling.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userText],
            ],
            'tools' => $tools,
            'tool_choice' => 'auto',
        ];

        try {
            $resp = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->post($base . '/chat/completions', $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'LLM Anfrage fehlgeschlagen', 'detail' => $e->getMessage()];
        }

        if ($resp->failed()) {
            return ['ok' => false, 'message' => 'LLM Fehler', 'detail' => $resp->json() ?: $resp->body()];
        }
        $data = $resp->json();
        $choice = $data['choices'][0] ?? [];
        $toolCall = $choice['message']['tool_calls'][0] ?? null;
        if (!$toolCall) {
            return ['ok' => false, 'message' => 'Kein Tool-Vorschlag'];
        }
        $intent = $toolCall['function']['name'] ?? null;
        $argsJson = $toolCall['function']['arguments'] ?? '{}';
        $slots = json_decode($argsJson, true) ?: [];

        // Impact & confirm aus Registry lookup
        $meta = $this->findCommandMeta($intent);
        $impact = $meta['impact'] ?? 'low';
        $confirmRequired = $meta['confirmRequired'] ?? false;

        return [
            'ok' => true,
            'intent' => $intent,
            'slots' => $slots,
            'impact' => $impact,
            'confirmRequired' => $confirmRequired,
        ];
    }

    protected function buildToolsFromRegistry(): array
    {
        $schemas = CommandRegistry::exportFunctionSchemas();
        $tools = [];
        foreach ($schemas as $s) {
            $tools[] = [
                'type' => 'function',
                'function' => $s,
            ];
        }
        return $tools;
    }

    protected function findCommandMeta(?string $key): array
    {
        if (!$key) return [];
        foreach (CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }
}


