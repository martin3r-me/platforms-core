<?php

namespace Platform\Core\Verbalization\Template;

/**
 * Registry fuer Erzaehlvorlagen.
 *
 * Module registrieren ihre Templates im ServiceProvider::boot.
 * Verbalizer fragt: "wer kann subject->type X behandeln?"
 *
 * Wenn kein Template registriert ist, faellt der Verbalizer auf
 * eine generische Default-Vorlage zurueck (siehe Verbalizer::resolveTemplate).
 */
class TemplateRegistry
{
    /** @var array<string, NarrativeTemplate> */
    protected array $templates = [];

    public function register(NarrativeTemplate $template): void
    {
        $this->templates[$template->handles()] = $template;
    }

    public function resolve(string $subjectType): ?NarrativeTemplate
    {
        return $this->templates[$subjectType] ?? null;
    }

    public function registered(): array
    {
        return array_keys($this->templates);
    }
}
