<?php

namespace Platform\Core\Contracts;

interface DocumentDataResolverContract
{
    /**
     * Entity type this resolver handles (e.g. 'invoice', 'offer')
     */
    public function getEntityType(): string;

    /**
     * Resolve an entity into template data.
     *
     * @param mixed $entity The domain entity (e.g. Invoice model)
     * @param array $context Additional context (team, user, etc.)
     * @return array Template data key-value pairs
     */
    public function resolve(mixed $entity, array $context = []): array;
}
