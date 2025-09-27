<?php

namespace Platform\Core\Services;

class AgentActivity
{
    public string $step;
    public string $tool;
    public array $parameters;
    public array $result;
    public string $status; // 'running', 'success', 'error'
    public string $message;
    public float $duration;
    
    public function __construct(
        string $step,
        string $tool = '',
        array $parameters = [],
        array $result = [],
        string $status = 'running',
        string $message = '',
        float $duration = 0.0
    ) {
        $this->step = $step;
        $this->tool = $tool;
        $this->parameters = $parameters;
        $this->result = $result;
        $this->status = $status;
        $this->message = $message;
        $this->duration = $duration;
    }
    
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'tool' => $this->tool,
            'parameters' => $this->parameters,
            'result' => $this->result,
            'status' => $this->status,
            'message' => $this->message,
            'duration' => $this->duration,
        ];
    }
    
    public function getStatusIcon(): string
    {
        return match($this->status) {
            'running' => '🔄',
            'success' => '✅',
            'error' => '❌',
            default => '⏳'
        };
    }
    
    public function getFormattedMessage(): string
    {
        $icon = $this->getStatusIcon();
        $message = $this->message ?: $this->step;
        
        if ($this->tool) {
            $message .= " ({$this->tool})";
        }
        
        if ($this->duration > 0) {
            $message .= " ({$this->duration}ms)";
        }
        
        return "{$icon} {$message}";
    }
}
