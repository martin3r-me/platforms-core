<?php

namespace Platform\Core\Contracts;

use Platform\Core\Contracts\ToolContext;

/**
 * Contract für Context-Enricher
 * 
 * Erweitert ToolContext mit zusätzlichen Informationen
 */
interface ContextEnricherContract
{
    /**
     * Erweitert einen ToolContext
     * 
     * @param ToolContext $context Original-Context
     * @return ToolContext Erweiterter Context
     */
    public function enrich(ToolContext $context): ToolContext;

    /**
     * Priorität des Enrichers (höher = wird zuerst ausgeführt)
     */
    public function getPriority(): int;
}

