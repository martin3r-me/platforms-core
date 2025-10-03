<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;

class SecurityHashKeyCommand extends Command
{
    protected $signature = 'security:hash-key 
        {--generate : Generate a new HASH_KEY (base64)}
        {--add-previous= : Add a previous hash key (base64 or plain) to PREVIOUS_HASH_KEYS}
        {--write : Attempt to write changes into .env}
    ';

    protected $description = 'Generate or manage HASH_KEY and PREVIOUS_HASH_KEYS for hashing rotation.';

    public function handle(): int
    {
        $envPath = base_path('.env');
        $currentEnv = file_exists($envPath) ? file_get_contents($envPath) : '';

        $newHashKey = null;
        if ($this->option('generate')) {
            $random = base64_encode(random_bytes(32));
            $newHashKey = 'base64:'.$random;
            $this->line('New HASH_KEY: '.$newHashKey);
        }

        $addPrev = $this->option('add-previous');
        if ($addPrev) {
            $this->line('Previous hash key to add: '.$addPrev);
        }

        if (! $this->option('write')) {
            $this->warn('Dry run. Use --write to update .env');
            $this->line('To apply:');
            if ($newHashKey) {
                $this->line('  HASH_KEY='.$newHashKey);
            }
            if ($addPrev) {
                $existing = env('PREVIOUS_HASH_KEYS', '');
                $merged = trim($existing ? ($existing.','.$addPrev) : $addPrev, ',');
                $this->line('  PREVIOUS_HASH_KEYS='.$merged);
            }
            return self::SUCCESS;
        }

        // Write mode
        $newEnv = $currentEnv;
        if ($newHashKey) {
            if (preg_match('/^HASH_KEY=.*/m', $newEnv)) {
                $newEnv = preg_replace('/^HASH_KEY=.*/m', 'HASH_KEY='.$newHashKey, $newEnv);
            } else {
                $newEnv .= (str_ends_with($newEnv, "\n") ? '' : "\n").'HASH_KEY='.$newHashKey."\n";
            }
        }

        if ($addPrev) {
            $existing = env('PREVIOUS_HASH_KEYS', '');
            $list = array_values(array_filter(array_map('trim', explode(',', $existing ?: ''))));
            $list[] = $addPrev;
            $list = array_values(array_unique(array_filter($list)));
            $merged = implode(',', $list);

            if (preg_match('/^PREVIOUS_HASH_KEYS=.*/m', $newEnv)) {
                $newEnv = preg_replace('/^PREVIOUS_HASH_KEYS=.*/m', 'PREVIOUS_HASH_KEYS='.$merged, $newEnv);
            } else {
                $newEnv .= (str_ends_with($newEnv, "\n") ? '' : "\n").'PREVIOUS_HASH_KEYS='.$merged."\n";
            }
        }

        if ($newEnv !== $currentEnv) {
            file_put_contents($envPath, $newEnv);
            $this->info('.env updated. Remember to clear config cache (php artisan config:clear)');
        } else {
            $this->line('No changes written.');
        }

        return self::SUCCESS;
    }
}


