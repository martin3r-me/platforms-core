<?php

namespace Platform\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Platform\Core\Contracts\HasContextDateTimes;

/**
 * Validiert einen context_type für CoreContextDateTime gegen die Whitelist
 * config('core.context_date_times.attachable_models') UND das Marker-Interface
 * {@see HasContextDateTimes}.
 *
 * Lesson Learned aus Issue #147: context_type bei ContextFile wurde nie
 * validiert – jede beliebige Klasse war ein gültiges morphTo-Ziel. Diese Regel
 * schließt beide Lücken: die Whitelist verhindert unbekannte/beliebige
 * Klassen, das Interface stellt sicher, dass die Zielklasse Context-Date-Times
 * überhaupt anbietet.
 */
class AttachableContextType implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Das Feld :attribute muss eine gültige Model-Klasse sein.');

            return;
        }

        $whitelist = (array) config('core.context_date_times.attachable_models', []);

        if (! in_array($value, $whitelist, true)) {
            $fail('Das Feld :attribute ist nicht für Context-Date-Times freigegeben.');

            return;
        }

        if (! class_exists($value) || ! is_a($value, HasContextDateTimes::class, true)) {
            $fail('Das Feld :attribute erfüllt nicht die Anforderungen für Context-Date-Times.');
        }
    }
}
