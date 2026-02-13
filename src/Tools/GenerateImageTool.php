<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ImageGenerationService;

/**
 * Tool zur KI-Bildgenerierung via DALL-E
 *
 * Generiert Bilder basierend auf Text-Prompts und kann diese
 * optional direkt an Context-Objekte anhängen.
 */
class GenerateImageTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.images.GENERATE';
    }

    public function getDescription(): string
    {
        return 'Generiert ein Bild mit KI (DALL-E) basierend auf einer Textbeschreibung. '
            . 'Kann das generierte Bild optional direkt an ein Context-Objekt (Task, Ticket, etc.) anhängen. '
            . 'Unterstützt verschiedene Größen, Qualitätsstufen und Stile.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'Detaillierte Beschreibung des gewünschten Bildes. Je genauer die Beschreibung, desto besser das Ergebnis. Englisch wird empfohlen.',
                ],
                'size' => [
                    'type' => 'string',
                    'enum' => ['1024x1024', '1536x1024', '1024x1536', 'auto'],
                    'description' => 'Bildgröße. 1024x1024 (Quadrat), 1536x1024 (Landscape), 1024x1536 (Portrait), auto (Model entscheidet). Standard: 1024x1024',
                ],
                'quality' => [
                    'type' => 'string',
                    'enum' => ['low', 'standard', 'high'],
                    'description' => 'Bildqualität. "low" für schnelle Generierung, "standard" für normale Qualität, "high" für maximale Details. Standard: standard',
                ],
                'style' => [
                    'type' => 'string',
                    'enum' => ['vivid', 'natural'],
                    'description' => 'Bildstil. "vivid" für lebhafte, dramatische Bilder, "natural" für realistischere Bilder. Standard: vivid',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Vollqualifizierter Model-Klassenname zum direkten Anhängen, z.B. "Platform\\Planner\\Models\\PlannerTask"',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Context-Objekts zum direkten Anhängen. Nur zusammen mit context_type verwenden.',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            $user = $context->user;

            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            // Validate prompt
            $prompt = $arguments['prompt'] ?? null;
            if (!$prompt || strlen($prompt) < 3) {
                return ToolResult::error('prompt ist erforderlich und muss mindestens 3 Zeichen haben', 'VALIDATION_ERROR');
            }

            // Optional parameters
            $size = $arguments['size'] ?? '1024x1024';
            $quality = $arguments['quality'] ?? 'standard';
            $style = $arguments['style'] ?? 'vivid';
            $contextType = $arguments['context_type'] ?? null;
            $contextId = $arguments['context_id'] ?? null;

            // Validate context parameters (both or none)
            if (($contextType && !$contextId) || (!$contextType && $contextId)) {
                return ToolResult::error(
                    'context_type und context_id müssen beide angegeben werden oder beide leer sein',
                    'VALIDATION_ERROR'
                );
            }

            $service = app(ImageGenerationService::class);

            $options = [
                'size' => $size,
                'quality' => $quality,
                'style' => $style,
            ];

            // Generate and optionally store
            if ($contextType && $contextId) {
                // Generate and attach to context
                $result = $service->generateAndStore(
                    $prompt,
                    $contextType,
                    (int) $contextId,
                    $user->id,
                    $team->id,
                    $options
                );

                return ToolResult::success([
                    'id' => $result['id'],
                    'token' => $result['token'],
                    'url' => $result['url'],
                    'width' => $result['width'],
                    'height' => $result['height'],
                    'mime_type' => $result['mime_type'],
                    'file_size' => $result['file_size'],
                    'variants_count' => $result['variants_count'],
                    'revised_prompt' => $result['revised_prompt'],
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                    'attached' => true,
                    'hint' => "Bild erfolgreich generiert und an {$contextType}:{$contextId} angehängt mit {$result['variants_count']} Varianten.",
                ]);
            } else {
                // Generate only (no storage)
                $result = $service->generate($prompt, $options);

                // gpt-image-1 may return base64 instead of URL
                if ($result['url']) {
                    return ToolResult::success([
                        'url' => $result['url'],
                        'revised_prompt' => $result['revised_prompt'],
                        'attached' => false,
                        'hint' => 'Bild erfolgreich generiert. URL ist temporär (ca. 1 Stunde gültig). '
                            . 'Nutze core.context.files.CREATE mit dieser URL zum dauerhaften Speichern, '
                            . 'oder rufe dieses Tool erneut mit context_type/context_id auf.',
                    ]);
                } else {
                    // Base64 returned - must be stored to be useful
                    return ToolResult::error(
                        'Bild wurde als Base64 generiert. Bitte context_type und context_id angeben um das Bild zu speichern.',
                        'STORAGE_REQUIRED'
                    );
                }
            }
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (\Exception $e) {
            return ToolResult::error('Fehler bei der Bildgenerierung: ' . $e->getMessage(), 'GENERATION_ERROR');
        }
    }
}
