<?php

namespace Platform\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Algedonic-Signal (Stafford Beer): "Schmerz" eines Menschen,
 * der alle hierarchischen Stufen ueberspringt und direkt das
 * normative Top-Level (S5) erreichen muss.
 *
 * Core dispatched dieses Event. Module wie Organization registrieren
 * Listener und entscheiden, was daraus wird (z.B. OrganizationSignal
 * mit vsm_level=s5, Carrier-Root als Owner, kurze Deadline).
 *
 * Bewusst primitive Payload — kein Domain-Modell aus anderen Modulen,
 * damit Core keine Abhaengigkeit zu Organization aufbaut.
 */
class AlgedonicTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $message,
        public int $userId,
        public int $teamId,
        public ?int $entityId = null,
        public ?int $perspectiveEntityId = null,
    ) {}
}
