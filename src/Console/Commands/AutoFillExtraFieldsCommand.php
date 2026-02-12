<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\Jobs\AutoFillExtraFieldsJob;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;

class AutoFillExtraFieldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'extra-fields:auto-fill
                            {--limit=100 : Maximale Anzahl zu verarbeitender Datensätze}
                            {--type= : Nur bestimmten context_type verarbeiten}
                            {--source= : Nur bestimmte auto_fill_source (llm|websearch)}
                            {--dry-run : Zeigt nur an, was verarbeitet würde}
                            {--sync : Führt Jobs synchron aus statt zu queuen}';

    /**
     * The console command description.
     */
    protected $description = 'Sucht und füllt Extra-Felder automatisch via LLM oder WebSearch';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isSync = $this->option('sync');
        $limit = (int) $this->option('limit');
        $typeFilter = $this->option('type');
        $sourceFilter = $this->option('source');

        $this->info('🔄 Extra-Fields AutoFill gestartet...');

        if ($isDryRun) {
            $this->info('   (DRY-RUN Modus - keine Änderungen)');
        }

        $this->newLine();

        // Find all definitions with auto_fill_source configured
        $definitionsQuery = CoreExtraFieldDefinition::query()
            ->whereNotNull('auto_fill_source');

        if ($typeFilter) {
            $definitionsQuery->where('context_type', $typeFilter);
        }

        if ($sourceFilter) {
            $definitionsQuery->where('auto_fill_source', $sourceFilter);
        }

        $definitions = $definitionsQuery->get();

        if ($definitions->isEmpty()) {
            $this->info('📋 Keine Extra-Feld-Definitionen mit AutoFill konfiguriert.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$definitions->count()} Definition(en) mit AutoFill gefunden.");
        $this->newLine();

        // Group definitions by context_type for efficient querying
        $definitionsByType = $definitions->groupBy('context_type');

        $totalFieldables = 0;
        $jobsDispatched = 0;

        foreach ($definitionsByType as $contextType => $typeDefinitions) {
            if (!class_exists($contextType)) {
                $this->warn("   ⚠️  Klasse nicht gefunden: {$contextType}");
                continue;
            }

            $this->info("🔍 Verarbeite: " . class_basename($contextType));

            $definitionIds = $typeDefinitions->pluck('id')->toArray();

            // Find fieldables that have empty values for these auto-fill definitions
            // We need to find models that either:
            // 1. Have no value record for the definition
            // 2. Have a value record with null/empty value

            // Get all fieldables of this type that have at least one team_id matching our definitions
            $teamIds = $typeDefinitions->pluck('team_id')->unique()->toArray();

            // Build subquery to find fieldables needing auto-fill
            $fieldablesWithValues = CoreExtraFieldValue::query()
                ->whereIn('definition_id', $definitionIds)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->select('fieldable_type', 'fieldable_id', 'definition_id');

            // Get all potential fieldables from the model table
            // This requires knowing the model structure - we'll use a different approach:
            // Find all existing field values and check which have empty auto-fill fields

            $fieldablesToProcess = $this->findFieldablesNeedingAutoFill($contextType, $definitionIds, $teamIds, $limit);

            $count = count($fieldablesToProcess);
            $totalFieldables += $count;

            $this->info("   → {$count} Datensatz/Datensätze mit leeren AutoFill-Feldern");

            if ($count === 0) {
                continue;
            }

            if (!$isDryRun) {
                foreach ($fieldablesToProcess as $fieldable) {
                    if ($isSync) {
                        // Run synchronously
                        try {
                            dispatch_sync(new AutoFillExtraFieldsJob($contextType, $fieldable['id']));
                            $this->line("   ✓ Verarbeitet: #{$fieldable['id']}");
                        } catch (\Throwable $e) {
                            $this->error("   ✗ Fehler bei #{$fieldable['id']}: {$e->getMessage()}");
                        }
                    } else {
                        // Queue the job
                        AutoFillExtraFieldsJob::dispatch($contextType, $fieldable['id']);
                    }
                    $jobsDispatched++;
                }
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info("🔍 DRY-RUN: {$totalFieldables} Datensatz/Datensätze würden verarbeitet.");
        } else {
            $queueInfo = $isSync ? 'synchron verarbeitet' : 'in Queue eingereiht';
            $this->info("✅ {$jobsDispatched} AutoFill-Jobs {$queueInfo}.");
        }

        return Command::SUCCESS;
    }

    /**
     * Find fieldables that need auto-fill
     */
    private function findFieldablesNeedingAutoFill(string $contextType, array $definitionIds, array $teamIds, int $limit): array
    {
        // Strategy: Find all models that have at least one extra field value (any definition)
        // but are missing values for auto-fill definitions

        // Get all unique fieldables of this type that have ANY extra field value
        // This ensures we only process models that are "active" in the extra fields system
        $allFieldables = DB::table('core_extra_field_values')
            ->where('fieldable_type', $contextType)
            ->orWhere('fieldable_type', (new $contextType)->getMorphClass())
            ->select('fieldable_id')
            ->distinct()
            ->limit($limit * 2) // Get more to filter
            ->get()
            ->pluck('fieldable_id')
            ->toArray();

        if (empty($allFieldables)) {
            // Also check models directly if they have team_id
            try {
                $modelQuery = $contextType::query();

                if (in_array('team_id', (new $contextType)->getFillable())) {
                    $modelQuery->whereIn('team_id', $teamIds);
                }

                $allFieldables = $modelQuery
                    ->limit($limit * 2)
                    ->pluck('id')
                    ->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        }

        if (empty($allFieldables)) {
            return [];
        }

        // Now find which of these are missing auto-fill values
        $fieldablesWithFilledAutoFill = CoreExtraFieldValue::query()
            ->whereIn('definition_id', $definitionIds)
            ->whereIn('fieldable_id', $allFieldables)
            ->where(function ($q) use ($contextType) {
                $q->where('fieldable_type', $contextType)
                    ->orWhere('fieldable_type', (new $contextType)->getMorphClass());
            })
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->groupBy('fieldable_id')
            ->havingRaw('COUNT(DISTINCT definition_id) = ?', [count($definitionIds)])
            ->pluck('fieldable_id')
            ->toArray();

        // Fieldables needing auto-fill = all - those with all fields filled
        $needsAutoFill = array_diff($allFieldables, $fieldablesWithFilledAutoFill);

        return array_slice(
            array_map(fn($id) => ['id' => $id], $needsAutoFill),
            0,
            $limit
        );
    }
}
