<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\CoreCommandRun;
use Platform\Core\Services\CommandDispatcher;
use Platform\Core\Services\CommandGateway;
use Platform\Core\Services\IntentMatcher;
use Platform\Core\Services\LlmPlanner;
use Livewire\Attributes\On;

class CursorPanel extends Component
{
    public bool $open = false;
    public string $input = '';
    public bool $forceExecute = false;

    public array $bubbles = []; // transient: plan/result messages

    public function toggle(): void
    {
        $this->open = !$this->open;
    }

    #[On('cursor-toggle')]
    public function openFromNavbar(): void
    {
        $this->toggle();
    }

    public function run(): void
    {
        $text = trim($this->input);
        if ($text === '') return;

        // Bubble: user input
        $this->bubbles[] = ['role' => 'user', 'text' => $text];

        // Plan per LLM
        $planner = new LlmPlanner();
        $plan = $planner->plan($text);
        $this->bubbles[] = ['role' => 'assistant', 'type' => 'plan', 'data' => $plan];

        if (($plan['ok'] ?? false) && ($plan['intent'] ?? null)) {
            $shouldExecute = $this->forceExecute || empty($plan['confirmRequired']);
            if ($shouldExecute) {
                $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
                $matched = [
                    'command' => $this->findCommandByKey($plan['intent']),
                    'slots' => $plan['slots'] ?? [],
                ];
                $result = $gateway->executeMatched($matched, auth()->user(), $this->forceExecute);
                $this->bubbles[] = ['role' => 'tool', 'type' => 'result', 'data' => $result];
                if (($result['ok'] ?? false) && !empty($result['navigate'])) {
                    $url = $result['navigate'];
                    $this->redirect($url, navigate: true);
                    return;
                }
            } else {
                // Confirm bubble
                $this->bubbles[] = ['role' => 'assistant', 'type' => 'confirm', 'data' => [
                    'intent' => $plan['intent'],
                    'impact' => $plan['impact'] ?? 'medium',
                    'slots' => $plan['slots'] ?? [],
                ]];
            }
        } else {
            $this->bubbles[] = ['role' => 'assistant', 'type' => 'error', 'data' => $plan];
        }

        $this->input = '';
    }

    public function confirmAndRun(string $intent, array $slots = []): void
    {
        $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
        $matched = [
            'command' => $this->findCommandByKey($intent),
            'slots' => $slots,
        ];
        $result = $gateway->executeMatched($matched, auth()->user(), true);
        $this->bubbles[] = ['role' => 'tool', 'type' => 'result', 'data' => $result];
        if (($result['ok'] ?? false) && !empty($result['navigate'])) {
            $url = $result['navigate'];
            $this->redirect($url, navigate: true);
        }
    }

    protected function findCommandByKey(string $key): array
    {
        foreach (\Platform\Core\Registry\CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }

    public function getRecentRunsProperty()
    {
        return CoreCommandRun::query()
            ->when(auth()->user(), fn($q) => $q->where('user_id', auth()->id()))
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('platform::livewire.cursor-panel');
    }
}


