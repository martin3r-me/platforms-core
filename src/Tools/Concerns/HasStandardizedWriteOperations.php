<?php

namespace Platform\Core\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait für standardisierte Write-Operationen (Update/Delete)
 * 
 * Stellt sicher, dass:
 * - ID immer erforderlich und bindend ist
 * - Model existiert und gefunden wird
 * - Zugriff geprüft wird
 * - Versionierung aktiviert ist
 */
trait HasStandardizedWriteOperations
{
    /**
     * Validiert und findet ein Model für Update/Delete
     * 
     * @param array $arguments Tool-Argumente
     * @param ToolContext $context Tool-Kontext
     * @param string $idField Name des ID-Feldes (z.B. 'project_id', 'task_id')
     * @param string $modelClass Vollständiger Model-Klassennamen
     * @param string $notFoundErrorCode Error-Code wenn nicht gefunden
     * @param string $notFoundErrorMessage Error-Message wenn nicht gefunden
     * @return array ['model' => Model|null, 'error' => ToolResult|null]
     */
    protected function validateAndFindModel(
        array $arguments,
        ToolContext $context,
        string $idField,
        string $modelClass,
        string $notFoundErrorCode = 'NOT_FOUND',
        string $notFoundErrorMessage = 'Element nicht gefunden.'
    ): array {
        // Prüfe, ob ID vorhanden ist
        if (empty($arguments[$idField])) {
            return [
                'model' => null,
                'error' => ToolResult::error(
                    'VALIDATION_ERROR',
                    "{$idField} ist erforderlich. Nutze das entsprechende GET-Tool um Elemente zu finden."
                ),
            ];
        }

        $id = (int)$arguments[$idField];

        // Prüfe, ob ID gültig ist
        if ($id <= 0) {
            return [
                'model' => null,
                'error' => ToolResult::error(
                    'VALIDATION_ERROR',
                    "Ungültige {$idField}. Die ID muss eine positive Zahl sein."
                ),
            ];
        }

        // Finde Model
        /** @var Model|null $model */
        $model = $modelClass::find($id);

        if (!$model) {
            return [
                'model' => null,
                'error' => ToolResult::error(
                    $notFoundErrorCode,
                    $notFoundErrorMessage . " Nutze das entsprechende GET-Tool um alle verfügbaren Elemente zu sehen."
                ),
            ];
        }

        return [
            'model' => $model,
            'error' => null,
        ];
    }

    /**
     * Prüft Zugriff auf ein Model
     * 
     * @param Model $model Das Model
     * @param ToolContext $context Tool-Kontext
     * @param callable|null $accessCheckCallback Optional: Custom Access-Check-Callback
     * @return ToolResult|null Error-Result wenn kein Zugriff, sonst null
     */
    protected function checkAccess(
        Model $model,
        ToolContext $context,
        ?callable $accessCheckCallback = null
    ): ?ToolResult {
        // Wenn Custom-Callback vorhanden, nutze diesen
        if ($accessCheckCallback !== null) {
            $hasAccess = $accessCheckCallback($model, $context);
            if (!$hasAccess) {
                return ToolResult::error(
                    'ACCESS_DENIED',
                    'Du hast keine Berechtigung, dieses Element zu bearbeiten oder zu löschen.'
                );
            }
            return null;
        }

        // Standard: Prüfe über Policy (wenn verfügbar)
        if (method_exists($model, 'can')) {
            $action = $this->getAccessAction(); // 'update' oder 'delete'
            if (!$context->user->can($action, $model)) {
                return ToolResult::error(
                    'ACCESS_DENIED',
                    'Du hast keine Berechtigung, dieses Element zu bearbeiten oder zu löschen.'
                );
            }
        }

        return null;
    }

    /**
     * Gibt die Access-Action zurück (für Policy-Checks)
     * 
     * @return string 'update' oder 'delete'
     */
    protected function getAccessAction(): string
    {
        // Kann in abgeleiteten Klassen überschrieben werden
        return 'update';
    }

    /**
     * Erstellt eine Reason-String für Versionierung
     * 
     * @param string $operation 'created', 'updated', 'deleted'
     * @param Model $model Das Model
     * @return string Reason-String
     */
    protected function createVersionReason(string $operation, Model $model): string
    {
        $modelName = class_basename(get_class($model));
        $modelId = $model->id;
        
        return sprintf(
            'LLM: %s %s (ID: %d)',
            ucfirst($operation),
            $modelName,
            $modelId
        );
    }
}

