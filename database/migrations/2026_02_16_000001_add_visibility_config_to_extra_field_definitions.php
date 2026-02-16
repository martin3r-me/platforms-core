<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a dedicated visibility_config column to core_extra_field_definitions.
 *
 * This formalizes the conditional visibility feature (depends_on_field, operator, value)
 * that was previously stored within the generic 'options' JSON column.
 *
 * The migration is backwards-compatible:
 * - Existing fields without conditions remain unaffected (NULL = always visible)
 * - Existing visibility configs stored in options.visibility are migrated to the new column
 * - The new column stores structured JSON: {enabled, logic, groups[{logic, conditions[{field, operator, value}]}]}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->json('visibility_config')->nullable()->after('options')
                ->comment('Conditional visibility rules: {enabled, logic, groups[{logic, conditions[{field, operator, value}]}]}');
        });

        // Migrate existing visibility configs from options.visibility to the new column
        $definitions = \Illuminate\Support\Facades\DB::table('core_extra_field_definitions')
            ->whereNotNull('options')
            ->get();

        foreach ($definitions as $definition) {
            $options = json_decode($definition->options, true);

            if (isset($options['visibility']) && !empty($options['visibility'])) {
                $visibilityConfig = $options['visibility'];

                // Remove visibility from options
                unset($options['visibility']);

                \Illuminate\Support\Facades\DB::table('core_extra_field_definitions')
                    ->where('id', $definition->id)
                    ->update([
                        'visibility_config' => json_encode($visibilityConfig),
                        'options' => empty($options) ? null : json_encode($options),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Migrate visibility_config back to options.visibility
        $definitions = \Illuminate\Support\Facades\DB::table('core_extra_field_definitions')
            ->whereNotNull('visibility_config')
            ->get();

        foreach ($definitions as $definition) {
            $options = $definition->options ? json_decode($definition->options, true) : [];
            $visibilityConfig = json_decode($definition->visibility_config, true);

            $options['visibility'] = $visibilityConfig;

            \Illuminate\Support\Facades\DB::table('core_extra_field_definitions')
                ->where('id', $definition->id)
                ->update([
                    'options' => json_encode($options),
                ]);
        }

        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->dropColumn('visibility_config');
        });
    }
};
