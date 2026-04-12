<?php

namespace Platform\Core\Contracts;

interface AgendaRenderable
{
    /**
     * Convert the model to an agenda item representation.
     *
     * @return array{title: string, description: ?string, icon: ?string, color: ?string, status: ?string, status_color: ?string, url: ?string, meta: array}
     */
    public function toAgendaItem(): array;
}
