<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Platform\Core\Models\CorePublicFormLink;

trait HasPublicFormLink
{
    public function publicFormLink(): MorphOne
    {
        return $this->morphOne(CorePublicFormLink::class, 'linkable');
    }

    public function getOrCreatePublicFormLink(): CorePublicFormLink
    {
        return $this->publicFormLink ?? $this->publicFormLink()->create([
            'team_id' => $this->team_id,
            'is_active' => true,
        ]);
    }
}
