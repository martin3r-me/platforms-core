<?php

namespace Platform\Core\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Kontext für Tool-Ausführung
 * 
 * Enthält alle relevanten Informationen für die Tool-Ausführung
 * und ermöglicht später Tool-Chaining
 */
class ToolContext
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly ?object $team = null,
        public readonly array $metadata = []
    ) {}

    /**
     * Erstellt einen Kontext aus dem aktuellen Auth-Context
     */
    public static function fromAuth(): self
    {
        $user = auth()->user();
        if (!$user) {
            throw new \RuntimeException('User must be authenticated');
        }

        $team = method_exists($user, 'currentTeam') ? $user->currentTeam : null;

        return new self($user, $team);
    }

    /**
     * Erstellt einen neuen Context (Factory-Methode)
     */
    public static function create(
        ?Authenticatable $user = null,
        ?object $team = null,
        array $metadata = []
    ): self {
        if ($user === null) {
            $user = auth()->user();
            if (!$user) {
                throw new \RuntimeException('User must be authenticated');
            }
        }

        return new self($user, $team, $metadata);
    }

    /**
     * Fügt Metadaten hinzu (für späteres Tool-Chaining)
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->user,
            $this->team,
            array_merge($this->metadata, $metadata)
        );
    }
}

