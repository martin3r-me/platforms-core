<?php

namespace Platform\Core\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Marker-Interface für Models, an die {@see \Platform\Core\Models\CoreContextDateTime}
 * angehängt werden darf.
 *
 * Wird von der Validation-Rule {@see \Platform\Core\Rules\AttachableContextType}
 * zusätzlich zur Config-Whitelist (config('core.context_date_times.attachable_models'))
 * geprüft: ein context_type muss BEIDES sein – whitelisted UND tatsächlich in der
 * Lage, Context-Date-Times bereitzustellen. Lesson Learned aus Issue #147: eine
 * reine String-Whitelist (context_type bei ContextFile) validiert nicht, ob die
 * Zielklasse den erwarteten Vertrag überhaupt erfüllt.
 *
 * Implementiert wird das Interface i.d.R. über das Trait
 * {@see \Platform\Core\Traits\HasContextDateTimes}.
 */
interface HasContextDateTimes
{
    public function contextDateTimes(): MorphMany;
}
