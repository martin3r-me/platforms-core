<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ensures a Personal Access Client exists for the 'users' provider.
     * Required for Laravel Passport 13.x token generation.
     */
    public function up(): void
    {
        if (! Schema::hasTable('oauth_clients')) {
            return;
        }

        $connection = config('passport.connection') ?? config('database.default');

        // Check if a Personal Access Client with provider 'users' already exists
        $exists = DB::connection($connection)
            ->table('oauth_clients')
            ->where('grant_types', 'like', '%personal_access%')
            ->where('provider', 'users')
            ->where('revoked', false)
            ->exists();

        if ($exists) {
            return;
        }

        // Update existing Personal Access Client if provider is NULL
        $updated = DB::connection($connection)
            ->table('oauth_clients')
            ->where('grant_types', 'like', '%personal_access%')
            ->whereNull('provider')
            ->where('revoked', false)
            ->update(['provider' => 'users']);

        if ($updated > 0) {
            return;
        }

        // Create new Personal Access Client
        DB::connection($connection)
            ->table('oauth_clients')
            ->insert([
                'id' => Str::uuid()->toString(),
                'name' => config('app.name', 'Platform').' Personal Access Client',
                'secret' => null,
                'provider' => 'users',
                'redirect_uris' => '[]',
                'grant_types' => '["personal_access"]',
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't remove the client on rollback to avoid breaking existing tokens
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('passport.connection');
    }
};
