<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;

class SimpleToolPlayground extends Component
{
    public $message = '';
    public $messages = [];
    public $isStreaming = false;
    public $streamingContent = ''; // Aktueller Streaming-Content
    public $currentTool = null; // Aktuell ausgeführte Tool

    public function mount()
    {
        // Initialisierung
    }

    public function sendMessage()
    {
        if (empty(trim($this->message)) || $this->isStreaming) {
            return;
        }

        $userMessage = trim($this->message);
        $this->message = '';
        
        // Füge User-Message hinzu
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $this->isStreaming = true;
        $this->streamingContent = '';
        $this->currentTool = null;
        
        // Dispatch Event für Frontend (startet SSE)
        $this->dispatch('start-stream', message: $userMessage);
    }

    // Wird vom Frontend aufgerufen, wenn SSE Delta kommt
    #[On('stream-delta')]
    public function handleStreamDelta($delta)
    {
        $this->streamingContent .= $delta;
    }

    // Wird vom Frontend aufgerufen, wenn Tool startet
    #[On('tool-start')]
    public function handleToolStart($tool)
    {
        $this->currentTool = $tool;
        if (!empty($this->streamingContent)) {
            $this->streamingContent .= "\n\n🔧 Führe Tool aus: {$tool}...\n";
        }
    }

    // Wird vom Frontend aufgerufen, wenn Tool fertig ist
    #[On('tool-complete')]
    public function handleToolComplete($tool)
    {
        $this->streamingContent .= " ✅\n";
        $this->currentTool = null;
    }

    // Wird vom Frontend aufgerufen, wenn Stream fertig ist
    #[On('stream-complete')]
    public function handleStreamComplete($content, $chatHistory = null)
    {
        // Füge Assistant-Response hinzu
        if (!empty($content)) {
            // Prüfe ob letzte Message bereits Assistant ist
            $lastMsg = end($this->messages);
            if ($lastMsg && $lastMsg['role'] === 'assistant') {
                // Update bestehende Message
                $this->messages[count($this->messages) - 1]['content'] = $content;
            } else {
                // Neue Message hinzufügen
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                ];
            }
        }

        // Update Chat-Historie falls vom Server zurückgegeben
        if ($chatHistory && is_array($chatHistory)) {
            $this->messages = $chatHistory;
        }

        $this->isStreaming = false;
        $this->streamingContent = '';
        $this->currentTool = null;
    }

    // Wird vom Frontend aufgerufen bei Fehler
    #[On('stream-error')]
    public function handleStreamError($error)
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => "❌ Fehler: " . ($error ?? 'Unbekannter Fehler'),
        ];
        $this->isStreaming = false;
        $this->streamingContent = '';
        $this->currentTool = null;
    }

    public function render()
    {
        return view('platform::livewire.simple-tool-playground')
            ->layout('platform::layouts.app');
    }
}

