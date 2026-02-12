<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Services\OpenAiService;

class AutoFillExtraFieldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180; // 3 minutes
    public $tries = 2;

    public function __construct(
        private string $fieldableType,
        private int $fieldableId
    ) {}

    public function handle(OpenAiService $openAi): void
    {
        // Resolve the model
        if (!class_exists($this->fieldableType)) {
            Log::warning('AutoFillExtraFieldsJob: Model class not found', [
                'fieldable_type' => $this->fieldableType,
            ]);
            return;
        }

        $model = $this->fieldableType::find($this->fieldableId);
        if (!$model) {
            Log::warning('AutoFillExtraFieldsJob: Model not found', [
                'fieldable_type' => $this->fieldableType,
                'fieldable_id' => $this->fieldableId,
            ]);
            return;
        }

        // Get team ID
        $teamId = $this->getTeamIdFromModel($model);
        if (!$teamId) {
            Log::warning('AutoFillExtraFieldsJob: No team ID found', [
                'fieldable_type' => $this->fieldableType,
                'fieldable_id' => $this->fieldableId,
            ]);
            return;
        }

        // Get all definitions with auto_fill for this context
        $definitions = CoreExtraFieldDefinition::query()
            ->forTeam($teamId)
            ->forContext($this->fieldableType, $model->id)
            ->whereNotNull('auto_fill_source')
            ->get();

        if ($definitions->isEmpty()) {
            return;
        }

        // Get existing values
        $existingValues = CoreExtraFieldValue::query()
            ->where('fieldable_type', $model->getMorphClass())
            ->where('fieldable_id', $model->id)
            ->get()
            ->keyBy('definition_id');

        // Build context from filled fields
        $context = $this->buildContext($model, $definitions, $existingValues);

        // Process each auto-fill definition that doesn't have a value yet
        foreach ($definitions as $definition) {
            $existingValue = $existingValues->get($definition->id);

            // Skip if already has a value
            if ($existingValue && $existingValue->value !== null && $existingValue->value !== '') {
                continue;
            }

            try {
                $value = match ($definition->auto_fill_source) {
                    'llm' => $this->autoFillWithLlm($openAi, $definition, $context),
                    'websearch' => $this->autoFillWithWebSearch($openAi, $definition, $context),
                    default => null,
                };

                if ($value !== null && $value !== '') {
                    $this->saveAutoFilledValue($model, $definition, $value);

                    Log::info('AutoFillExtraFieldsJob: Field auto-filled', [
                        'fieldable_type' => $this->fieldableType,
                        'fieldable_id' => $this->fieldableId,
                        'definition_id' => $definition->id,
                        'field_name' => $definition->name,
                        'source' => $definition->auto_fill_source,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('AutoFillExtraFieldsJob: Error filling field', [
                    'fieldable_type' => $this->fieldableType,
                    'fieldable_id' => $this->fieldableId,
                    'definition_id' => $definition->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build context string from model and filled fields
     */
    private function buildContext(Model $model, $definitions, $existingValues): string
    {
        $contextParts = [];

        // Add model basic info
        $contextParts[] = "Objekt-Typ: " . class_basename($this->fieldableType);
        $contextParts[] = "ID: " . $this->fieldableId;

        // Try to add common model attributes
        $commonAttributes = ['name', 'title', 'description', 'email', 'company', 'firma', 'phone', 'telefon', 'address', 'adresse', 'city', 'stadt', 'country', 'land', 'website', 'url'];
        foreach ($commonAttributes as $attr) {
            if (isset($model->$attr) && $model->$attr) {
                $contextParts[] = ucfirst($attr) . ": " . $model->$attr;
            }
        }

        // Add filled extra fields
        foreach ($definitions as $definition) {
            $value = $existingValues->get($definition->id);
            if ($value && $value->value !== null && $value->value !== '') {
                $typedValue = $value->typed_value;
                if (is_array($typedValue)) {
                    $typedValue = implode(', ', $typedValue);
                }
                $contextParts[] = $definition->label . ": " . $typedValue;
            }
        }

        return implode("\n", $contextParts);
    }

    /**
     * Auto-fill using LLM
     */
    private function autoFillWithLlm(OpenAiService $openAi, CoreExtraFieldDefinition $definition, string $context): ?string
    {
        $prompt = $definition->auto_fill_prompt ?: "Fülle das Feld '{$definition->label}' basierend auf dem Kontext aus.";

        $systemPrompt = <<<PROMPT
Du bist ein Daten-Assistent. Deine Aufgabe ist es, fehlende Felder basierend auf dem vorhandenen Kontext auszufüllen.

WICHTIG:
- Antworte NUR mit dem Wert für das Feld, ohne Erklärungen
- Wenn du den Wert nicht sicher bestimmen kannst, antworte mit "UNKNOWN"
- Bei Auswahlfeldern: Antworte nur mit einer der verfügbaren Optionen
- Bei Zahlenfeldern: Antworte nur mit einer Zahl
- Bei Ja/Nein-Feldern: Antworte mit "1" für Ja oder "0" für Nein
PROMPT;

        $userMessage = <<<MSG
Kontext:
{$context}

Feld das ausgefüllt werden soll: {$definition->label}
Feldtyp: {$definition->type}
MSG;

        // Add options for select fields
        if ($definition->type === 'select' && isset($definition->options['choices'])) {
            $choices = implode(', ', $definition->options['choices']);
            $userMessage .= "\nVerfügbare Optionen: {$choices}";
        }

        $userMessage .= "\n\nAnweisung: {$prompt}";

        $response = $openAi->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ], 'gpt-4o', [
            'max_tokens' => 200,
            'tools' => false,
        ]);

        $value = $this->extractTextFromResponse($response);

        // Check if LLM returned "UNKNOWN"
        if (strtoupper(trim($value)) === 'UNKNOWN') {
            return null;
        }

        return $this->normalizeValueForType($value, $definition);
    }

    /**
     * Auto-fill using WebSearch
     */
    private function autoFillWithWebSearch(OpenAiService $openAi, CoreExtraFieldDefinition $definition, string $context): ?string
    {
        $searchPrompt = $definition->auto_fill_prompt ?: "Finde Informationen für '{$definition->label}'";

        // First, use LLM to generate a good search query
        $queryResponse = $openAi->chat([
            ['role' => 'system', 'content' => 'Generiere eine optimale Google-Suchanfrage basierend auf dem Kontext. Antworte NUR mit der Suchanfrage, ohne Erklärungen.'],
            ['role' => 'user', 'content' => "Kontext:\n{$context}\n\nZiel: {$searchPrompt}"],
        ], 'gpt-4o-mini', [
            'max_tokens' => 100,
            'tools' => false,
        ]);

        $searchQuery = trim($this->extractTextFromResponse($queryResponse));

        if (empty($searchQuery)) {
            return null;
        }

        // Use web_search tool if available, otherwise fall back to LLM with context
        // For now, we'll simulate web search by using LLM with search-like context
        // In production, this would integrate with actual web search API

        $webSearchPrompt = <<<PROMPT
Du simulierst eine Web-Suche. Basierend auf der Suchanfrage und dem Kontext, was wäre das wahrscheinlichste Ergebnis für das gesuchte Feld?

Suchanfrage: {$searchQuery}

Kontext:
{$context}

Feld: {$definition->label}
Feldtyp: {$definition->type}

Antworte NUR mit dem Wert, ohne Erklärungen. Bei Unsicherheit antworte mit "UNKNOWN".
PROMPT;

        $response = $openAi->chat([
            ['role' => 'user', 'content' => $webSearchPrompt],
        ], 'gpt-4o', [
            'max_tokens' => 200,
            'tools' => false,
        ]);

        $value = $this->extractTextFromResponse($response);

        if (strtoupper(trim($value)) === 'UNKNOWN') {
            return null;
        }

        return $this->normalizeValueForType($value, $definition);
    }

    /**
     * Extract text content from OpenAI response
     */
    private function extractTextFromResponse(array $response): string
    {
        if (isset($response['content'])) {
            return trim($response['content']);
        }

        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output) {
                if (isset($output['content']) && is_array($output['content'])) {
                    foreach ($output['content'] as $content) {
                        if (isset($content['text'])) {
                            return trim($content['text']);
                        }
                    }
                }
            }
        }

        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        return '';
    }

    /**
     * Normalize value based on field type
     */
    private function normalizeValueForType(string $value, CoreExtraFieldDefinition $definition): ?string
    {
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        return match ($definition->type) {
            'number' => is_numeric($value) ? $value : null,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'ja', 'yes']) ? '1' : '0',
            'select' => $this->normalizeSelectValue($value, $definition),
            default => $value,
        };
    }

    /**
     * Normalize select value to match available choices
     */
    private function normalizeSelectValue(string $value, CoreExtraFieldDefinition $definition): ?string
    {
        $choices = $definition->options['choices'] ?? [];

        if (empty($choices)) {
            return $value;
        }

        // Exact match
        if (in_array($value, $choices)) {
            return $value;
        }

        // Case-insensitive match
        foreach ($choices as $choice) {
            if (strtolower($choice) === strtolower($value)) {
                return $choice;
            }
        }

        // Partial match
        foreach ($choices as $choice) {
            if (str_contains(strtolower($choice), strtolower($value)) ||
                str_contains(strtolower($value), strtolower($choice))) {
                return $choice;
            }
        }

        return null;
    }

    /**
     * Save the auto-filled value
     */
    private function saveAutoFilledValue(Model $model, CoreExtraFieldDefinition $definition, string $value): void
    {
        $fieldValue = CoreExtraFieldValue::query()
            ->where('definition_id', $definition->id)
            ->where('fieldable_type', $model->getMorphClass())
            ->where('fieldable_id', $model->id)
            ->first();

        if (!$fieldValue) {
            $fieldValue = new CoreExtraFieldValue([
                'definition_id' => $definition->id,
                'fieldable_type' => $model->getMorphClass(),
                'fieldable_id' => $model->id,
            ]);
        }

        $fieldValue->setTypedValue($value);
        $fieldValue->auto_filled = true;
        $fieldValue->auto_filled_at = now();
        $fieldValue->save();
    }

    /**
     * Get team ID from model
     */
    private function getTeamIdFromModel(Model $model): ?int
    {
        if (isset($model->team_id)) {
            return $model->team_id;
        }

        // Try to get from relationship
        if (method_exists($model, 'team') && $model->team) {
            return $model->team->id;
        }

        return null;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AutoFillExtraFieldsJob: Job failed permanently', [
            'fieldable_type' => $this->fieldableType,
            'fieldable_id' => $this->fieldableId,
            'error' => $e->getMessage(),
        ]);
    }
}
