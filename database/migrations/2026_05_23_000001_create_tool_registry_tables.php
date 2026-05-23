<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tool_registry_entries')) {
        Schema::create('tool_registry_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('kind', 20)->nullable();
            $table->string('module', 50)->nullable();
            $table->string('namespace', 50)->nullable();
            $table->string('tier', 20);
            $table->text('intent');
            $table->text('description')->nullable();
            $table->jsonb('required_params')->nullable();
            $table->jsonb('optional_params')->nullable();
            $table->string('cost_class', 30);
            $table->decimal('cost_per_call_eur', 10, 4)->nullable();
            $table->boolean('read_only')->default(false);
            $table->boolean('deprecated')->default(false);
            $table->string('successor_name', 255)->nullable();
            $table->integer('usage_7d')->default(0);
            $table->integer('usage_30d')->default(0);
            $table->integer('usage_90d')->default(0);
            $table->timestamps();

            $table->index('namespace');
            $table->index('tier');
            $table->index('module');
            $table->index('deprecated');
        });
        }

        if (!Schema::hasTable('tool_registry_tags')) {
        Schema::create('tool_registry_tags', function (Blueprint $table) {
            $table->foreignId('tool_registry_entry_id')
                ->constrained('tool_registry_entries')
                ->cascadeOnDelete();
            $table->string('tag', 100);

            $table->primary(['tool_registry_entry_id', 'tag']);
            $table->index('tag');
        });
        }

        if (!Schema::hasTable('tool_registry_requires')) {
        Schema::create('tool_registry_requires', function (Blueprint $table) {
            $table->foreignId('tool_registry_entry_id')
                ->constrained('tool_registry_entries')
                ->cascadeOnDelete();
            $table->string('required_tool_name', 255);
            $table->string('for_param', 100)->default('');

            $table->primary(
                ['tool_registry_entry_id', 'required_tool_name', 'for_param'],
                'tool_registry_requires_pk'
            );
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_registry_requires');
        Schema::dropIfExists('tool_registry_tags');
        Schema::dropIfExists('tool_registry_entries');
    }
};
