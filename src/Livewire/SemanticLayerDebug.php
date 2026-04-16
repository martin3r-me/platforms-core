<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Module;
use Platform\Core\SemanticLayer\DTOs\ResolvedLayer;
use Platform\Core\SemanticLayer\Exceptions\InvalidLayerSchemaException;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\SemanticLayer\Services\SemanticLayerScaffold;

/**
 * Debug-Panel und leichter Editor für den Semantic Base Layer.
 *
 * - Read-only Vorschau des aktuell resolvten Layers
 * - Schnelles Togglen der enabled_modules, Status-Switcher
 * - Level-A-Editor: neuen Layer anlegen oder neue Version zu bestehendem Layer
 *   hinzufügen. Formular mit Live-Preview, Live-Token-Count und Schema-Validierung.
 *
 * Editieren bestehender Versionen ist bewusst nicht vorgesehen —
 * Versionen sind immutable. Änderungen erzeugen immer eine neue Version.
 */
class SemanticLayerDebug extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $layers = [];

    /** @var array<int, string> */
    public array $availableModules = [];

    public ?string $selectedModule = null;

    public ?array $resolvedForCurrentTeam = null;

    public ?array $resolvedForNoTeam = null;

    // ---------- Edit-State ----------

    /** null | 'new-layer' | 'new-version' */
    public ?string $editMode = null;

    public ?int $editLayerId = null;

    /** 'global' | 'team' */
    public string $editScope = 'global';

    public ?int $editTeamId = null;

    public string $formSemver = '1.0.0';

    /** 'major' | 'minor' | 'patch' */
    public string $formVersionType = 'minor';

    public string $formPerspektive = '';

    public string $formTon = '';

    public string $formHeuristiken = '';

    public string $formNegativRaum = '';

    public string $formNotes = '';

    /** @var array<int, string> */
    public array $formErrors = [];

    public ?string $formWarning = null;

    public ?string $formPreviewBlock = null;

    public int $formTokenCount = 0;

    public ?string $currentTeamLabel = null;

    public ?int $currentTeamId = null;

    public ?string $lastSaveMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        $currentTeam = $user?->currentTeamRelation;

        if (! $currentTeam) {
            abort(403);
        }

        $userRole = $currentTeam->users()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot
            ?->role;

        if ($userRole !== TeamRole::OWNER->value) {
            abort(403);
        }

        $this->currentTeamId = $currentTeam->id;
        $this->currentTeamLabel = $currentTeam->name;

        $this->loadData();
    }

    public function selectModule(?string $module): void
    {
        $this->selectedModule = $module === '' ? null : $module;
        $this->loadData();
    }

    public function toggleModule(int $layerId, string $module): void
    {
        $layer = SemanticLayer::find($layerId);
        if (! $layer) {
            return;
        }

        $enabled = $layer->enabled_modules ?? [];
        $wasEnabled = in_array($module, $enabled, true);

        if ($wasEnabled) {
            $enabled = array_values(array_filter($enabled, fn ($m) => $m !== $module));
        } else {
            $enabled[] = $module;
        }

        $layer->enabled_modules = array_values(array_unique($enabled));
        $layer->save();

        SemanticLayerAudit::record(
            layerId: $layer->id,
            action: $wasEnabled ? 'disabled_module' : 'enabled_module',
            versionId: $layer->current_version_id,
            diff: null,
            userId: Auth::id(),
            context: ['module' => $module],
        );

        $this->loadData();
    }

    public function setStatus(int $layerId, string $status): void
    {
        $allowed = [
            SemanticLayer::STATUS_DRAFT,
            SemanticLayer::STATUS_PILOT,
            SemanticLayer::STATUS_PRODUCTION,
            SemanticLayer::STATUS_ARCHIVED,
        ];
        if (! in_array($status, $allowed, true)) {
            return;
        }

        $layer = SemanticLayer::find($layerId);
        if (! $layer) {
            return;
        }

        $previous = $layer->status;
        if ($previous === $status) {
            return;
        }

        $layer->status = $status;
        $layer->save();

        SemanticLayerAudit::record(
            layerId: $layer->id,
            action: 'status_changed',
            versionId: $layer->current_version_id,
            diff: [[
                'field' => 'status',
                'op' => 'changed',
                'from' => $previous,
                'to' => $status,
            ]],
            userId: Auth::id(),
            context: null,
        );

        $this->loadData();
    }

    // ---------- Edit-Actions ----------

    /**
     * Start a new layer form for the given scope.
     * $scope = 'global' | 'team'
     */
    public function openNewLayer(string $scope): void
    {
        if (! in_array($scope, [SemanticLayer::SCOPE_GLOBAL, SemanticLayer::SCOPE_TEAM], true)) {
            return;
        }

        // Existiert schon ein Layer für diesen Scope? Dann stattdessen neue Version.
        $existing = $scope === SemanticLayer::SCOPE_GLOBAL
            ? SemanticLayer::global()
            : ($this->currentTeamId ? SemanticLayer::forTeam($this->currentTeamId) : null);

        if ($existing) {
            $this->openNewVersion($existing->id);
            return;
        }

        $this->resetForm();
        $this->editMode = 'new-layer';
        $this->editScope = $scope;
        $this->editTeamId = $scope === SemanticLayer::SCOPE_TEAM ? $this->currentTeamId : null;
        $this->formSemver = $scope === SemanticLayer::SCOPE_GLOBAL ? '1.0.0' : '0.1.0';
        $this->formVersionType = 'minor';
        $this->recalcPreview();
    }

    public function openNewVersion(int $layerId): void
    {
        $layer = SemanticLayer::with('currentVersion')->find($layerId);
        if (! $layer) {
            return;
        }

        $this->resetForm();
        $this->editMode = 'new-version';
        $this->editLayerId = $layer->id;
        $this->editScope = $layer->scope_type;
        $this->editTeamId = $layer->scope_id;

        $v = $layer->currentVersion;
        if ($v) {
            $this->formPerspektive = (string) $v->perspektive;
            $this->formTon = implode("\n", $v->ton ?? []);
            $this->formHeuristiken = implode("\n", $v->heuristiken ?? []);
            $this->formNegativRaum = implode("\n", $v->negativ_raum ?? []);
            $this->formSemver = $this->suggestNextSemver($v->semver, 'patch');
            $this->formVersionType = 'patch';
        } else {
            $this->formSemver = $layer->scope_type === SemanticLayer::SCOPE_GLOBAL ? '1.0.0' : '0.1.0';
            $this->formVersionType = 'minor';
        }

        $this->recalcPreview();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->editMode = null;
    }

    public function updatedFormVersionType(): void
    {
        // Automatisches Anheben des SemVer-Vorschlags basierend auf Basis-Version
        if ($this->editMode === 'new-version' && $this->editLayerId) {
            $layer = SemanticLayer::with('currentVersion')->find($this->editLayerId);
            $base = $layer?->currentVersion?->semver;
            if ($base) {
                $this->formSemver = $this->suggestNextSemver($base, $this->formVersionType);
            }
        }
    }

    public function updatedFormPerspektive(): void
    {
        $this->recalcPreview();
    }

    public function updatedFormTon(): void
    {
        $this->recalcPreview();
    }

    public function updatedFormHeuristiken(): void
    {
        $this->recalcPreview();
    }

    public function updatedFormNegativRaum(): void
    {
        $this->recalcPreview();
    }

    public function updatedFormSemver(): void
    {
        $this->recalcPreview();
    }

    public function saveVersion(): void
    {
        $this->formErrors = [];
        $this->lastSaveMessage = null;

        $payload = $this->buildPayload();

        /** @var LayerSchemaValidator $validator */
        $validator = app(LayerSchemaValidator::class);

        try {
            $validator->validate($payload);
        } catch (InvalidLayerSchemaException $e) {
            $this->formErrors = $e->errors;
            return;
        }

        // SemVer-Format
        if (! preg_match('/^\d+\.\d+\.\d+$/', $this->formSemver)) {
            $this->formErrors[] = "SemVer muss dem Format MAJOR.MINOR.PATCH entsprechen (z.B. 1.0.0).";
            return;
        }

        if (! in_array($this->formVersionType, [
            SemanticLayerVersion::TYPE_MAJOR,
            SemanticLayerVersion::TYPE_MINOR,
            SemanticLayerVersion::TYPE_PATCH,
        ], true)) {
            $this->formErrors[] = "Ungültiger Version-Type: {$this->formVersionType}";
            return;
        }

        /** @var SemanticLayerScaffold $scaffold */
        $scaffold = app(SemanticLayerScaffold::class);

        $rendered = $scaffold->render(
            perspektive: $payload['perspektive'],
            ton: $payload['ton'],
            heuristiken: $payload['heuristiken'],
            negativRaum: $payload['negativ_raum'],
            versionChain: [$this->formSemver],
        );
        $tokenCount = $validator->estimateTokens($rendered);

        try {
            $result = DB::transaction(function () use ($payload, $tokenCount) {
                $layer = $this->editMode === 'new-version'
                    ? SemanticLayer::find($this->editLayerId)
                    : SemanticLayer::firstOrCreate(
                        ['scope_type' => $this->editScope, 'scope_id' => $this->editTeamId],
                        ['status' => SemanticLayer::STATUS_DRAFT, 'enabled_modules' => []],
                    );

                if (! $layer) {
                    throw new \RuntimeException('Layer nicht gefunden.');
                }

                if ($layer->versions()->where('semver', $this->formSemver)->exists()) {
                    throw new \RuntimeException("Version {$this->formSemver} existiert bereits für diesen Scope.");
                }

                // Diff gegen aktuelle Version (falls vorhanden) — für Audit
                $diff = null;
                if ($layer->currentVersion) {
                    $diff = $this->buildDiff($layer->currentVersion->payload(), $payload);
                }

                $version = SemanticLayerVersion::create([
                    'semantic_layer_id' => $layer->id,
                    'semver' => $this->formSemver,
                    'version_type' => $this->formVersionType,
                    'perspektive' => $payload['perspektive'],
                    'ton' => $payload['ton'],
                    'heuristiken' => $payload['heuristiken'],
                    'negativ_raum' => $payload['negativ_raum'],
                    'token_count' => $tokenCount,
                    'notes' => $this->formNotes !== '' ? $this->formNotes : null,
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]);

                // Auto-Aktivierung der neuen Version als current_version.
                // Status wird nicht verändert — bleibt draft, wenn es ein neuer Layer ist.
                $layer->current_version_id = $version->id;
                $layer->save();

                SemanticLayerAudit::record(
                    layerId: $layer->id,
                    action: $layer->wasRecentlyCreated ? 'created' : 'version_created',
                    versionId: $version->id,
                    diff: $diff,
                    userId: Auth::id(),
                    context: [
                        'semver' => $this->formSemver,
                        'scope' => $this->editScope,
                        'team_id' => $this->editTeamId,
                        'source' => 'debug-ui',
                    ],
                );

                return [
                    'layer_id' => $layer->id,
                    'semver' => $this->formSemver,
                    'token_count' => $tokenCount,
                ];
            });
        } catch (\Throwable $e) {
            $this->formErrors[] = $e->getMessage();
            return;
        }

        app(SemanticLayerResolver::class)->forgetCache();

        $this->lastSaveMessage = "Version v{$result['semver']} gespeichert und als aktiv markiert ({$result['token_count']} Token).";
        $this->resetForm();
        $this->editMode = null;
        $this->loadData();
    }

    // ---------- Helpers ----------

    protected function resetForm(): void
    {
        $this->editLayerId = null;
        $this->editScope = 'global';
        $this->editTeamId = null;
        $this->formSemver = '1.0.0';
        $this->formVersionType = 'minor';
        $this->formPerspektive = '';
        $this->formTon = '';
        $this->formHeuristiken = '';
        $this->formNegativRaum = '';
        $this->formNotes = '';
        $this->formErrors = [];
        $this->formWarning = null;
        $this->formPreviewBlock = null;
        $this->formTokenCount = 0;
    }

    /**
     * @return array{perspektive: string, ton: array<int,string>, heuristiken: array<int,string>, negativ_raum: array<int,string>}
     */
    protected function buildPayload(): array
    {
        return [
            'perspektive' => trim($this->formPerspektive),
            'ton' => $this->splitLines($this->formTon),
            'heuristiken' => $this->splitLines($this->formHeuristiken),
            'negativ_raum' => $this->splitLines($this->formNegativRaum),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function splitLines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));
        return $lines;
    }

    protected function recalcPreview(): void
    {
        $payload = $this->buildPayload();

        if ($payload['perspektive'] === '' && empty($payload['ton']) && empty($payload['heuristiken']) && empty($payload['negativ_raum'])) {
            $this->formPreviewBlock = null;
            $this->formTokenCount = 0;
            $this->formWarning = null;
            return;
        }

        /** @var SemanticLayerScaffold $scaffold */
        $scaffold = app(SemanticLayerScaffold::class);

        $rendered = $scaffold->render(
            perspektive: $payload['perspektive'] !== '' ? $payload['perspektive'] : '—',
            ton: $payload['ton'],
            heuristiken: $payload['heuristiken'],
            negativRaum: $payload['negativ_raum'],
            versionChain: [$this->formSemver !== '' ? $this->formSemver : '?'],
        );

        /** @var LayerSchemaValidator $validator */
        $validator = app(LayerSchemaValidator::class);

        $this->formPreviewBlock = $rendered;
        $this->formTokenCount = $validator->estimateTokens($rendered);
        $this->formWarning = $validator->checkTokenBudget($this->formTokenCount);
    }

    protected function suggestNextSemver(string $current, string $type): string
    {
        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $current, $m)) {
            return $current;
        }
        $maj = (int) $m[1];
        $min = (int) $m[2];
        $pat = (int) $m[3];

        return match ($type) {
            SemanticLayerVersion::TYPE_MAJOR => ($maj + 1) . '.0.0',
            SemanticLayerVersion::TYPE_MINOR => $maj . '.' . ($min + 1) . '.0',
            default => $maj . '.' . $min . '.' . ($pat + 1),
        };
    }

    /**
     * Strukturierter Diff für die Audit-Chain.
     *
     * @param  array<string, mixed> $from
     * @param  array<string, mixed> $to
     * @return array<int, array<string, mixed>>
     */
    protected function buildDiff(array $from, array $to): array
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

    protected function loadData(): void
    {
        $this->availableModules = Module::query()
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $rows = SemanticLayer::query()
            ->with(['currentVersion', 'team'])
            ->orderBy('scope_type')
            ->orderBy('scope_id')
            ->get();

        $this->layers = $rows->map(function (SemanticLayer $layer) {
            $v = $layer->currentVersion;
            return [
                'id' => $layer->id,
                'scope_type' => $layer->scope_type,
                'scope_id' => $layer->scope_id,
                'scope_label' => $layer->scope_type === SemanticLayer::SCOPE_GLOBAL
                    ? 'global'
                    : 'team · ' . ($layer->team?->name ?? ('#' . $layer->scope_id)),
                'status' => $layer->status,
                'enabled_modules' => $layer->enabled_modules ?? [],
                'current_semver' => $v?->semver,
                'token_count' => $v?->token_count,
                'version_count' => $layer->versions()->count(),
                'updated_at' => $layer->updated_at?->diffForHumans(),
            ];
        })->all();

        /** @var SemanticLayerResolver $resolver */
        $resolver = app(SemanticLayerResolver::class);
        $team = Auth::user()?->currentTeamRelation;

        $this->resolvedForCurrentTeam = $this->serializeResolved(
            $resolver->resolveFor($team, $this->selectedModule)
        );
        $this->resolvedForNoTeam = $this->serializeResolved(
            $resolver->resolveFor(null, $this->selectedModule)
        );
    }

    protected function serializeResolved(ResolvedLayer $r): ?array
    {
        if ($r->isEmpty()) {
            return null;
        }
        return $r->toArray();
    }

    public function hasGlobalLayer(): bool
    {
        foreach ($this->layers as $layer) {
            if ($layer['scope_type'] === SemanticLayer::SCOPE_GLOBAL) {
                return true;
            }
        }
        return false;
    }

    public function hasTeamLayer(): bool
    {
        foreach ($this->layers as $layer) {
            if ($layer['scope_type'] === SemanticLayer::SCOPE_TEAM && $layer['scope_id'] === $this->currentTeamId) {
                return true;
            }
        }
        return false;
    }

    public function render()
    {
        return view('platform::livewire.semantic-layer-debug')
            ->layout('platform::layouts.app');
    }
}
