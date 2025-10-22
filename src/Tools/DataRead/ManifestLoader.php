<?php

namespace Platform\Core\Tools\DataRead;

use Illuminate\Filesystem\Filesystem;

class ManifestLoader
{
    public function loadFromStorage(ProviderRegistry $registry): void
    {
        $entitiesDir = storage_path('app/ai/entities');
        $fs = new Filesystem();
        if (!$fs->isDirectory($entitiesDir)) {
            return; // nothing to load yet
        }

        foreach ($fs->files($entitiesDir) as $file) {
            if ($file->getExtension() !== 'json') { continue; }
            try {
                $raw = $fs->get($file->getRealPath());
                $data = json_decode($raw, true);
                if (!is_array($data) || empty($data['entity']) || empty($data['model'])) { continue; }
                $provider = new ManifestEntityProvider($data);
                $registry->register($provider);
            } catch (\Throwable $e) {
                \Log::warning('ManifestLoader skipped file '.$file->getFilename().': '.$e->getMessage());
                continue;
            }
        }
    }
}
