<?php

namespace Platform\Core\Contracts;

use Platform\Core\Support\MeetingBriefing;

/**
 * Ein Host, der Meetings an ihm mit einer Anforderung/einem Leitfaden brieft —
 * z. B. ein Gremium mit seinem LA-Leitfaden, ein Board mit seinen Regeln.
 *
 * Optional und getrennt vom {@see MeetingHost}-Vertrag (Interface-Segregation):
 * jeder Host trägt Herkunft, aber nur Governance-Hosts tragen ein Briefing. Die
 * Anforderung bleibt beim Host (abrufbar, nicht kopiert); das Meeting liest sie nur.
 */
interface BriefsMeetings
{
    /** Anforderung an Meetings an diesem Host; null → keine. */
    public function meetingBriefing(): ?MeetingBriefing;
}
