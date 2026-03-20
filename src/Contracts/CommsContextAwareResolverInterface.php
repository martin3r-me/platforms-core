<?php

namespace Platform\Core\Contracts;

/**
 * Optional interface for CommsContactResolvers that support
 * using the resolved contact itself as a thread context.
 *
 * When a resolver implements this interface, the
 * CommsThreadContextResolverService can use the contact type
 * as a fallback context (Strategy B: contact-as-context).
 */
interface CommsContextAwareResolverInterface
{
    /**
     * Get the contact model classes that are eligible to be used as thread context.
     *
     * @return array<string> Full class names, e.g. [CrmContact::class]
     */
    public function getContextEligibleContactTypes(): array;
}
