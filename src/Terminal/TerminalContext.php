<?php

namespace Platform\Core\Terminal;

/**
 * Immutable value object describing the Terminal's current context.
 *
 * The shell tracks contextType/contextId/subject/team as loose public props;
 * this wraps them into a typed payload that apps receive to decide availability
 * (isAvailable) and to hydrate themselves on mount.
 */
class TerminalContext
{
    public function __construct(
        public readonly ?int $teamId = null,
        public readonly ?string $contextType = null,
        public readonly ?int $contextId = null,
        public readonly ?string $subject = null,
        public readonly ?string $source = null,
    ) {}

    /** True when the terminal is pinned to a concrete context entity. */
    public function hasContext(): bool
    {
        return $this->contextType !== null && $this->contextId !== null;
    }

    /** True when the context points at the given morph type (e.g. 'organization'). */
    public function isType(string $type): bool
    {
        return $this->contextType === $type;
    }

    /** Serialize for handing down to a nested app component. */
    public function toArray(): array
    {
        return [
            'team_id'      => $this->teamId,
            'context_type' => $this->contextType,
            'context_id'   => $this->contextId,
            'subject'      => $this->subject,
            'source'       => $this->source,
        ];
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            teamId:      isset($payload['team_id']) ? (int) $payload['team_id'] : null,
            contextType: $payload['context_type'] ?? null,
            contextId:   isset($payload['context_id']) ? (int) $payload['context_id'] : null,
            subject:     $payload['subject'] ?? null,
            source:      $payload['source'] ?? null,
        );
    }
}
