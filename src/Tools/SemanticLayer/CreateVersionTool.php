<?php

namespace Platform\Core\Tools\SemanticLayer;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\Exceptions\InvalidLayerSchemaException;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\SemanticLayer\Services\SemanticLayerScaffold;

/**
 * core.semantic_layer.versions.POST
 *
 * Kernoperation des Semantic-Layer-MCP-Toolsets. Legt eine neue Version
 * an. Wenn noch kein Layer im Scope existiert, wird er automatisch
 * angelegt (Status: pilot, enabled_modules: []).
 *
 * Validiert via LayerSchemaValidator, rendert via SemanticLayerScaffold,
 * schreibt einen strukturierten Audit-Diff gegen die vorherige
 * current_version und aktiviert die neue Version automatisch als
 * current_version_id (Auto-Activate, abweichend zur Console).
 *
 * SemVer ist ein expliziter Parameter — kein Auto-Bump.
 *
 * Owner-only.
 */
class CreateVersionTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    public function __construct(
        private readonly LayerSchemaValidator $validator,
        private readonly SemanticLayerScaffold $scaffold,
        private readonly SemanticLayerResolver $resolver,
    ) {
    }

    public function getName(): string
    {
        return 'core.semantic_layer.versions.POST';
    }

    public function getDescription(): string
    {
        return 'Legt eine neue Version eines Semantic-Layers an (oder erstellt den Layer, falls noch keiner im Scope existiert). '
            . 'Validiert hart gegen das 4-Kanal-Schema (perspektive, ton, heuristiken, negativ_raum), rendert den Prompt-Block, '
            . 'aktiviert die neue Version automatisch als current_version und schreibt einen Audit-Diff. '
            . 'SemVer muss explizit als MAJOR.MINOR.PATCH-String mitgegeben werden — kein Auto-Bump. '
            . 'Owner-only. '
            . 'Bei Token-Count außerhalb des Soft-Bereichs (80–250) wird ein "budget_warning" im Output gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scope' => [
                    'type' => 'string',
                    'enum' => [SemanticLayer::SCOPE_GLOBAL, SemanticLayer::SCOPE_TEAM],
                    'description' => '"global" für den BHG-Core-Layer, "team" für den Venture-Extension-Layer. Default: "global".',
                ],
                'team_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Team-ID — nur bei scope=team relevant. Wenn nicht angegeben, wird der aktive Team-Kontext verwendet.',
                ],
                'semver' => [
                    'type' => 'string',
                    'description' => 'SemVer der neuen Version im Format MAJOR.MINOR.PATCH (z.B. "1.0.0"). Muss im Scope eindeutig sein.',
                ],
                'version_type' => [
                    'type' => 'string',
                    'enum' => [
                        SemanticLayerVersion::TYPE_MAJOR,
                        SemanticLayerVersion::TYPE_MINOR,
                        SemanticLayerVersion::TYPE_PATCH,
                    ],
                    'description' => 'Art der Änderung: "major" (Breaking), "minor" (Erweiterung), "patch" (Bugfix). Default: "minor".',
                ],
                'perspektive' => [
                    'type' => 'string',
                    'description' => 'Perspektive (1..500 Zeichen). Wer sind wir, wer redet hier?',
                ],
                'ton' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Ton-Anweisungen (1..12 Items, je 1..120 Zeichen). Wie reden wir?',
                ],
                'heuristiken' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Heuristiken im Zweifel (1..12 Items, je 1..200 Zeichen). Was tun, wenn unklar?',
                ],
                'negativ_raum' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Negativ-Raum (1..12 Items, je 1..120 Zeichen). Was wir nie sagen / sind.',
                ],
                'notes' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optionale freie Notiz zur Version (z.B. Begründung, Kontext, Quellen).',
                ],
            ],
            'required' => ['semver', 'perspektive', 'ton', 'heuristiken', 'negativ_raum'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if ($denied = $this->assertOwner($context)) {
                return $denied;
            }

            $scopeResult = $this->resolveScope($arguments, $context);
            if ($scopeResult instanceof ToolResult) {
                return $scopeResult;
            }
            [$scope, $teamId] = $scopeResult;

            // SemVer
            $semver = $arguments['semver'] ?? null;
            if (!is_string($semver) || $semver === '') {
                return ToolResult::error('VALIDATION_ERROR', 'semver ist erforderlich.');
            }
            if (!preg_match('/^\d+\.\d+\.\d+$/', $semver)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'semver muss dem Format MAJOR.MINOR.PATCH entsprechen (z.B. "1.0.0"). Erhalten: "' . $semver . '".'
                );
            }

            // Version-Type
            $versionType = $arguments['version_type'] ?? SemanticLayerVersion::TYPE_MINOR;
            if (!in_array($versionType, [
                SemanticLayerVersion::TYPE_MAJOR,
                SemanticLayerVersion::TYPE_MINOR,
                SemanticLayerVersion::TYPE_PATCH,
            ], true)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'Ungültiger version_type: "' . (string) $versionType . '". Erlaubt: major, minor, patch.'
                );
            }

            // Payload
            $payload = [
                'perspektive' => $arguments['perspektive'] ?? null,
                'ton' => $arguments['ton'] ?? null,
                'heuristiken' => $arguments['heuristiken'] ?? null,
                'negativ_raum' => $arguments['negativ_raum'] ?? null,
            ];

            try {
                $this->validator->validate($payload);
            } catch (InvalidLayerSchemaException $e) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'Schema-Validierung fehlgeschlagen: ' . implode('; ', $e->errors)
                );
            }

            // Render + Token-Count
            $rendered = $this->scaffold->render(
                perspektive: $payload['perspektive'],
                ton: $payload['ton'],
                heuristiken: $payload['heuristiken'],
                negativRaum: $payload['negativ_raum'],
                versionChain: [$semver],
            );
            $tokenCount = $this->validator->estimateTokens($rendered);
            $budgetWarning = $this->validator->checkTokenBudget($tokenCount);

            $notes = $arguments['notes'] ?? null;
            if ($notes === '') {
                $notes = null;
            }

            $userId = $context->user->id ?? null;

            try {
                $result = DB::transaction(function () use (
                    $scope,
                    $teamId,
                    $semver,
                    $versionType,
                    $payload,
                    $tokenCount,
                    $notes,
                    $userId
                ) {
                    $layer = SemanticLayer::firstOrCreate(
                        ['scope_type' => $scope, 'scope_id' => $teamId],
                        ['status' => SemanticLayer::STATUS_PILOT, 'enabled_modules' => []],
                    );
                    $layerWasNew = $layer->wasRecentlyCreated;

                    if ($layer->versions()->where('semver', $semver)->exists()) {
                        throw new VersionExistsException(
                            "Version {$semver} existiert bereits für scope={$scope}"
                            . ($scope === SemanticLayer::SCOPE_TEAM ? ", team_id={$teamId}" : '')
                            . '.'
                        );
                    }

                    // Diff gegen aktuelle Version (für Audit)
                    $diff = null;
                    if ($layer->currentVersion) {
                        $diff = $this->buildDiff($layer->currentVersion->payload(), $payload);
                    }

                    $version = SemanticLayerVersion::create([
                        'semantic_layer_id' => $layer->id,
                        'semver' => $semver,
                        'version_type' => $versionType,
                        'perspektive' => $payload['perspektive'],
                        'ton' => $payload['ton'],
                        'heuristiken' => $payload['heuristiken'],
                        'negativ_raum' => $payload['negativ_raum'],
                        'token_count' => $tokenCount,
                        'notes' => $notes,
                        'created_by' => $userId,
                        'created_at' => now(),
                    ]);

                    // Auto-Activate als current_version
                    $layer->current_version_id = $version->id;
                    $layer->save();

                    SemanticLayerAudit::record(
                        layerId: $layer->id,
                        action: $layerWasNew ? 'created' : 'version_created',
                        versionId: $version->id,
                        diff: $diff,
                        userId: $userId,
                        context: [
                            'semver' => $semver,
                            'scope' => $scope,
                            'team_id' => $teamId,
                            'source' => 'mcp',
                        ],
                    );

                    return [
                        'layer' => $layer,
                        'version' => $version,
                        'layer_was_new' => $layerWasNew,
                    ];
                });
            } catch (VersionExistsException $e) {
                return ToolResult::error('VERSION_EXISTS', $e->getMessage());
            }

            $this->resolver->forgetCache();

            $output = [
                'layer_id' => $result['layer']->id,
                'layer_was_new' => $result['layer_was_new'],
                'version_id' => $result['version']->id,
                'semver' => $semver,
                'version_type' => $versionType,
                'token_count' => $tokenCount,
                'rendered_block' => $rendered,
                'status' => $result['layer']->status,
                'enabled_modules' => $result['layer']->enabled_modules ?? [],
            ];
            if ($budgetWarning !== null) {
                $output['budget_warning'] = $budgetWarning;
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Version: ' . $e->getMessage());
        }
    }

    /**
     * Strukturierter Diff für die Audit-Chain. Identisch zur UI-Implementierung.
     *
     * @param  array<string, mixed> $from
     * @param  array<string, mixed> $to
     * @return array<int, array<string, mixed>>
     */
    private function buildDiff(array $from, array $to): array
    {
        $diff = [];

        if (($from['perspektive'] ?? null) !== ($to['perspektive'] ?? null)) {
            $diff[] = [
                'field' => 'perspektive',
                'op' => 'changed',
                'from' => $from['perspektive'] ?? null,
                'to' => $to['perspektive'] ?? null,
            ];
        }

        foreach (['ton', 'heuristiken', 'negativ_raum'] as $field) {
            $a = $from[$field] ?? [];
            $b = $to[$field] ?? [];
            $added = array_values(array_diff($b, $a));
            $removed = array_values(array_diff($a, $b));
            foreach ($added as $item) {
                $diff[] = ['field' => $field, 'op' => 'added', 'from' => null, 'to' => $item];
            }
            foreach ($removed as $item) {
                $diff[] = ['field' => $field, 'op' => 'removed', 'from' => $item, 'to' => null];
            }
        }

        return $diff;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'semantic_layer', 'create', 'version'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'confirmation_required' => false,
            'side_effects' => ['creates', 'updates'],
            'related_tools' => [
                'core.semantic_layer.layer.GET',
                'core.semantic_layer.layers.GET',
                'core.semantic_layer.resolved.GET',
                'core.semantic_layer.status.PATCH',
            ],
        ];
    }
}
