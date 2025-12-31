<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolRun;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Support\Facades\Log;

/**
 * Tool Run Service
 * 
 * Verwaltet persistente Multi-Step-Runs für replay- und resume-fähige Tool-Flows
 */
class ToolRunService
{
    /**
     * Erstellt einen neuen Tool-Run
     */
    public function createRun(
        string $conversationId,
        string $toolName,
        array $arguments,
        ToolContext $context,
        int $step = 0
    ): ToolRun {
        return ToolRun::create([
            'conversation_id' => $conversationId,
            'step' => $step,
            'tool_name' => $toolName,
            'arguments' => $arguments,
            'status' => ToolRun::STATUS_PENDING,
            'waiting_for_input' => false,
            'user_id' => $context->user?->id,
            'team_id' => $context->team?->id,
        ]);
    }
    
    /**
     * Aktualisiert einen Run mit User-Input-Status
     */
    public function updateRunWaitingInput(
        int $runId,
        array $inputOptions,
        string $nextTool,
        array $nextToolArgs
    ): ToolRun {
        $run = ToolRun::findOrFail($runId);
        
        $run->update([
            'status' => ToolRun::STATUS_WAITING_INPUT,
            'waiting_for_input' => true,
            'input_options' => $inputOptions,
            'next_tool' => $nextTool,
            'next_tool_args' => $nextToolArgs,
        ]);
        
        return $run;
    }
    
    /**
     * Setzt einen Run fort (nach User-Input)
     */
    public function resumeRun(
        int $runId,
        string $userInput,
        ?array $mergedArguments = null
    ): ToolRun {
        $run = ToolRun::findOrFail($runId);
        
        if ($run->status !== ToolRun::STATUS_WAITING_INPUT) {
            throw new \RuntimeException("Run ist nicht im waiting_input Status");
        }
        
        // Merge User-Input in Arguments
        $arguments = $mergedArguments ?? $run->next_tool_args ?? [];
        
        // Wenn User-Input numerisch ist, als ID verwenden
        if (is_numeric($userInput)) {
            $arguments['selected_id'] = (int)$userInput;
        } else {
            // Versuche JSON zu parsen
            $parsed = json_decode($userInput, true);
            if (is_array($parsed)) {
                $arguments = array_merge($arguments, $parsed);
            } else {
                $arguments['user_input'] = $userInput;
            }
        }
        
        $run->update([
            'status' => ToolRun::STATUS_PENDING,
            'waiting_for_input' => false,
            'arguments' => $arguments,
            'resumed_at' => now(),
        ]);
        
        return $run;
    }
    
    /**
     * Markiert einen Run als completed
     */
    public function completeRun(int $runId): ToolRun
    {
        $run = ToolRun::findOrFail($runId);
        
        $run->update([
            'status' => ToolRun::STATUS_COMPLETED,
            'waiting_for_input' => false,
        ]);
        
        return $run;
    }
    
    /**
     * Markiert einen Run als failed
     */
    public function failRun(int $runId, ?string $errorMessage = null): ToolRun
    {
        $run = ToolRun::findOrFail($runId);
        
        $run->update([
            'status' => ToolRun::STATUS_FAILED,
            'waiting_for_input' => false,
        ]);
        
        if ($errorMessage) {
            Log::error('[ToolRunService] Run fehlgeschlagen', [
                'run_id' => $runId,
                'error' => $errorMessage,
            ]);
        }
        
        return $run;
    }
    
    /**
     * Holt einen Run
     */
    public function getRun(int $runId): ?ToolRun
    {
        return ToolRun::find($runId);
    }
    
    /**
     * Holt den letzten Run für eine Conversation
     */
    public function getLastRunForConversation(string $conversationId): ?ToolRun
    {
        return ToolRun::forConversation($conversationId)
            ->orderBy('step', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }
    
    /**
     * Holt alle Runs für eine Conversation
     */
    public function getRunsForConversation(string $conversationId): array
    {
        return ToolRun::forConversation($conversationId)
            ->orderBy('step', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }
}

