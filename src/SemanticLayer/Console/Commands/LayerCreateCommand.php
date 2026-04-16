<?php

namespace Platform\Core\SemanticLayer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Platform\Core\SemanticLayer\Exceptions\InvalidLayerSchemaException;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\SemanticLayer\Services\SemanticLayerScaffold;

/**
 * php artisan layer:create --scope=global --semver=1.0.0
 * php artisan layer:create --scope=team --team-id=9 --semver=0.1.0
 *
 * Liest das Layer-Payload (4 Kanäle) aus:
 *   --from-file=/path/to/layer.json   ODER
 *   --from-stdin                      (payload via STDIN)
 *   --editor                          (default: öffnet $EDITOR mit Template)
 */
class LayerCreateCommand extends Command
{
    protected $signature = 'layer:create
                            {--scope=global : "global" oder "team"}
                            {--team-id= : Team-ID (nur bei scope=team erforderlich)}
                            {--semver=1.0.0 : SemVer der neuen Version}
                            {--version-type=minor : "major" | "minor" | "patch"}
                            {--from-file= : Pfad zu einer JSON-Datei mit dem Payload}
                            {--from-stdin : Payload via STDIN lesen}
                            {--editor : $EDITOR öffnen (Default, wenn nichts anderes gesetzt)}
                            {--notes= : Optionale Notizen zur Version}';

    protected $description = 'Erstellt einen neuen SemanticLayer oder eine neue Version eines bestehenden Layers.';

    public function handle(
        LayerSchemaValidator $validator,
        SemanticLayerScaffold $scaffold,
        SemanticLayerResolver $resolver,
    ): int {
        $scope = (string) $this->option('scope');
        if (!in_array($scope, [SemanticLayer::SCOPE_GLOBAL, SemanticLayer::SCOPE_TEAM], true)) {
            $this->error("Ungültiger --scope: {$scope}. Erlaubt: global | team");
            return self::FAILURE;
        }

        $teamId = null;
        if ($scope === SemanticLayer::SCOPE_TEAM) {
            $teamId = $this->option('team-id');
            if (!$teamId) {
                $this->error('--team-id ist erforderlich bei --scope=team');
                return self::FAILURE;
            }
            if (!Team::find($teamId)) {
                $this->error("Team #{$teamId} nicht gefunden");
                return self::FAILURE;
            }
        }

        $semver = (string) $this->option('semver');
        $versionType = (string) $this->option('version-type');
        if (!in_array($versionType, [
            SemanticLayerVersion::TYPE_MAJOR,
            SemanticLayerVersion::TYPE_MINOR,
            SemanticLayerVersion::TYPE_PATCH,
        ], true)) {
            $this->error("Ungültiger --version-type: {$versionType}");
            return self::FAILURE;
        }

        $payload = $this->loadPayload();
        if ($payload === null) {
            return self::FAILURE;
        }

        try {
            $validator->validate($payload);
        } catch (InvalidLayerSchemaException $e) {
            $this->error('Schema-Validierung fehlgeschlagen:');
            foreach ($e->errors as $err) {
                $this->line('  - ' . $err);
            }
            return self::FAILURE;
        }

        // Token-Budget-Check (soft)
        $rendered = $scaffold->render(
            perspektive: $payload['perspektive'],
            ton: $payload['ton'],
            heuristiken: $payload['heuristiken'],
            negativRaum: $payload['negativ_raum'],
            versionChain: [$semver],
        );
        $tokenCount = $validator->estimateTokens($rendered);
        $warning = $validator->checkTokenBudget($tokenCount);
        if ($warning !== null) {
            $this->warn('[Token-Budget] ' . $warning);
        }

        DB::transaction(function () use (
            $scope,
            $teamId,
            $semver,
            $versionType,
            $payload,
            $tokenCount,
            &$createdLayerId,
            &$createdVersionId
        ) {
            $layer = SemanticLayer::firstOrCreate(
                ['scope_type' => $scope, 'scope_id' => $teamId],
                ['status' => SemanticLayer::STATUS_DRAFT, 'enabled_modules' => []],
            );

            $exists = $layer->versions()->where('semver', $semver)->exists();
            if ($exists) {
                throw new \RuntimeException("Version {$semver} existiert bereits für diesen Scope");
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
                'notes' => $this->option('notes'),
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            SemanticLayerAudit::record(
                layerId: $layer->id,
                action: $layer->wasRecentlyCreated ? 'created' : 'version_created',
                versionId: $version->id,
                diff: null,
                userId: auth()->id(),
                context: ['semver' => $semver, 'scope' => $scope, 'team_id' => $teamId],
            );

            $createdLayerId = $layer->id;
            $createdVersionId = $version->id;
        });

        $resolver->forgetCache();

        $this->info("Layer-Version erstellt:");
        $this->line("  Scope:    {$scope}" . ($teamId ? " (team={$teamId})" : ''));
        $this->line("  SemVer:   {$semver}");
        $this->line("  Tokens:   {$tokenCount}");
        $this->line("  Layer-ID: {$createdLayerId}");
        $this->line("  Ver.-ID:  {$createdVersionId}");
        $this->newLine();
        $this->comment("Hinweis: Version ist noch nicht aktiv. Zum Aktivieren:");
        $this->line("  php artisan layer:activate --scope={$scope}" . ($teamId ? " --team-id={$teamId}" : '') . " --semver={$semver}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPayload(): ?array
    {
        $fromFile = $this->option('from-file');
        $fromStdin = (bool) $this->option('from-stdin');
        $useEditor = (bool) $this->option('editor') || (!$fromFile && !$fromStdin);

        $json = null;

        if ($fromFile) {
            if (!is_file($fromFile)) {
                $this->error("Datei nicht gefunden: {$fromFile}");
                return null;
            }
            $json = file_get_contents($fromFile);
        } elseif ($fromStdin) {
            $json = stream_get_contents(STDIN);
        } elseif ($useEditor) {
            $template = json_encode([
                'perspektive' => '',
                'ton' => [],
                'heuristiken' => [],
                'negativ_raum' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $tmp = tempnam(sys_get_temp_dir(), 'semantic-layer-') . '.json';
            file_put_contents($tmp, $template);

            $editor = getenv('EDITOR') ?: 'vi';
            $cmd = escapeshellcmd($editor) . ' ' . escapeshellarg($tmp);
            // @phpstan-ignore-next-line - shell editor launch
            passthru($cmd);

            $json = file_get_contents($tmp);
            @unlink($tmp);
        }

        if ($json === null || trim((string) $json) === '') {
            $this->error('Kein Payload erhalten.');
            return null;
        }

        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            $this->error('JSON-Parse-Fehler: ' . json_last_error_msg());
            return null;
        }
        return $data;
    }
}
