<?php

namespace Platform\Core\Services\Comms;

use Platform\Core\Contracts\CommsContactResolverInterface;
use Illuminate\Support\Facades\Log;

/**
 * Registry for contact resolvers.
 *
 * Modules can register their resolvers to link inbound communications
 * to contact entities. Resolvers are checked in priority order.
 */
class ContactResolverRegistry
{
    /**
     * @var CommsContactResolverInterface[]
     */
    private array $resolvers = [];

    /**
     * Whether resolvers are sorted by priority.
     */
    private bool $sorted = false;

    /**
     * Register a contact resolver.
     */
    public function register(CommsContactResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
        $this->sorted = false;

        Log::debug("[ContactResolverRegistry] Resolver registered", [
            'resolver' => get_class($resolver),
            'priority' => $resolver->priority(),
        ]);
    }

    /**
     * Resolve a contact by phone number.
     * Returns the first match from resolvers (ordered by priority).
     *
     * @param string $phone E.164 format phone number
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function resolveByPhone(string $phone): ?array
    {
        $this->ensureSorted();

        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolveByPhone($phone);
            if ($result !== null) {
                Log::debug("[ContactResolverRegistry] Resolved by phone", [
                    'phone' => $phone,
                    'resolver' => get_class($resolver),
                    'contact_type' => $result['type'] ?? null,
                    'contact_id' => $result['id'] ?? null,
                ]);
                return $result;
            }
        }

        return null;
    }

    /**
     * Resolve a contact by email address.
     * Returns the first match from resolvers (ordered by priority).
     *
     * @param string $email Email address
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function resolveByEmail(string $email): ?array
    {
        $this->ensureSorted();

        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolveByEmail($email);
            if ($result !== null) {
                Log::debug("[ContactResolverRegistry] Resolved by email", [
                    'email' => $email,
                    'resolver' => get_class($resolver),
                    'contact_type' => $result['type'] ?? null,
                    'contact_id' => $result['id'] ?? null,
                ]);
                return $result;
            }
        }

        return null;
    }

    /**
     * Create a contact from phone number using the first resolver that supports auto-create.
     *
     * @param string $phone E.164 format phone number
     * @param array $meta Additional metadata (source, channel_id, team_id, etc.)
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function createFromPhone(string $phone, array $meta = []): ?array
    {
        $this->ensureSorted();

        foreach ($this->resolvers as $resolver) {
            if (!$resolver->supportsAutoCreate()) {
                continue;
            }

            $result = $resolver->createFromPhone($phone, $meta);
            if ($result !== null) {
                Log::info("[ContactResolverRegistry] Created contact from phone", [
                    'phone' => $phone,
                    'resolver' => get_class($resolver),
                    'contact_type' => $result['type'] ?? null,
                    'contact_id' => $result['id'] ?? null,
                ]);
                return $result;
            }
        }

        return null;
    }

    /**
     * Create a contact from email address using the first resolver that supports auto-create.
     *
     * @param string $email Email address
     * @param array $meta Additional metadata (source, channel_id, team_id, etc.)
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function createFromEmail(string $email, array $meta = []): ?array
    {
        $this->ensureSorted();

        foreach ($this->resolvers as $resolver) {
            if (!$resolver->supportsAutoCreate()) {
                continue;
            }

            $result = $resolver->createFromEmail($email, $meta);
            if ($result !== null) {
                Log::info("[ContactResolverRegistry] Created contact from email", [
                    'email' => $email,
                    'resolver' => get_class($resolver),
                    'contact_type' => $result['type'] ?? null,
                    'contact_id' => $result['id'] ?? null,
                ]);
                return $result;
            }
        }

        return null;
    }

    /**
     * Resolve or create a contact by phone number.
     *
     * @param string $phone E.164 format phone number
     * @param array $meta Metadata for creation if needed
     * @param bool $autoCreate Whether to auto-create if not found
     * @return array|null
     */
    public function resolveOrCreateByPhone(string $phone, array $meta = [], bool $autoCreate = false): ?array
    {
        $result = $this->resolveByPhone($phone);

        if ($result === null && $autoCreate) {
            $result = $this->createFromPhone($phone, $meta);
        }

        return $result;
    }

    /**
     * Resolve or create a contact by email address.
     *
     * @param string $email Email address
     * @param array $meta Metadata for creation if needed
     * @param bool $autoCreate Whether to auto-create if not found
     * @return array|null
     */
    public function resolveOrCreateByEmail(string $email, array $meta = [], bool $autoCreate = false): ?array
    {
        $result = $this->resolveByEmail($email);

        if ($result === null && $autoCreate) {
            $result = $this->createFromEmail($email, $meta);
        }

        return $result;
    }

    /**
     * Check if any resolvers are registered.
     */
    public function hasResolvers(): bool
    {
        return !empty($this->resolvers);
    }

    /**
     * Get all registered resolvers.
     *
     * @return CommsContactResolverInterface[]
     */
    public function all(): array
    {
        $this->ensureSorted();
        return $this->resolvers;
    }

    /**
     * Sort resolvers by priority (highest first).
     */
    private function ensureSorted(): void
    {
        if ($this->sorted) {
            return;
        }

        usort($this->resolvers, function (CommsContactResolverInterface $a, CommsContactResolverInterface $b) {
            return $b->priority() <=> $a->priority(); // Descending
        });

        $this->sorted = true;
    }
}
