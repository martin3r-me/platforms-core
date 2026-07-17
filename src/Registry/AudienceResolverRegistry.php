<?php

namespace Platform\Core\Registry;

use Platform\Core\Contracts\AudienceResolverInterface;

/**
 * Sammelt AudienceResolver pro Ziel-Typ. Module registrieren ihre Resolver hier
 * (z.B. Organisation registriert 'org_entity'); Konsumenten (z.B. Academy)
 * fragen nur diese Registry und kennen die Ziel-Quellen nicht.
 */
class AudienceResolverRegistry
{
    /** @var array<string, AudienceResolverInterface> */
    private array $resolvers = [];

    public function register(AudienceResolverInterface $resolver): void
    {
        $this->resolvers[$resolver->type()] = $resolver;
    }

    public function supports(string $type): bool
    {
        return isset($this->resolvers[$type]);
    }

    /** @return array<int,string> */
    public function supportedTypes(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<int,int>  eindeutige User-IDs
     */
    public function resolve(string $type, int $targetId, array $options = [], ?int $teamId = null): array
    {
        $resolver = $this->resolvers[$type] ?? null;
        if (!$resolver) {
            return [];
        }

        return array_values(array_unique(array_map(
            'intval',
            $resolver->resolve($targetId, $options, $teamId)
        )));
    }

    public function label(string $type, int $targetId, ?int $teamId = null): ?string
    {
        return isset($this->resolvers[$type])
            ? $this->resolvers[$type]->label($targetId, $teamId)
            : null;
    }
}
