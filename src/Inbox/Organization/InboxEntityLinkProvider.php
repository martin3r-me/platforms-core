<?php

namespace Platform\Core\Inbox\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Platform\Core\Inbox\Enums\Channel;
use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Inbox\Models\InboxItem;
use Platform\Organization\Contracts\EntityLinkProvider;

/**
 * Inbox-Item als linkbares Objekt + Zeit-Träger am Org-Baum (morph `inbox_item`).
 *
 * Historie:
 *   - Phase A: Inbox-Item war Zeit-Kontext (context_type = InboxItem).
 *   - Phase C: Zeit zog aufs MEETING (context_type = Meeting); Cascade hier leer,
 *     um Doppelzählung über zwei Links (inbox_item UND meeting) zu vermeiden.
 *   - Phase 2 der Meetings-Auflösung (jetzt): das meetings-Modul fällt weg,
 *     „Termin = Inbox-Item". Die Ist-Zeit kehrt auf das Inbox-Item zurück
 *     (context_type = InboxItem). Weil der konkurrierende `meeting`-Morph mit dem
 *     Modul verschwindet, gibt es KEIN Doppelzählen mehr.
 *
 * MaterializeMeetingTimeCommand bucht je Teilnehmer-Item eine OrganizationTimeEntry
 * (context_type = InboxItem, context_id = item_id); der EntityTimeResolver rollt sie
 * über diesen Cascade auf den Knoten. Serie/Teilnehmer: der Knoten-Link propagiert
 * über die Unit (gleiche ical_uid) auf alle Vorkommen + Beteiligten — je Person ein
 * eigenes Item mit eigener Buchung, Summe am gemeinsamen Knoten.
 */
class InboxEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['inbox_item'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'inbox_item' => [
                'label' => 'Termine & Eingänge',
                'singular' => 'Eingang',
                'icon' => 'inbox',
                // Klick öffnet den Eintrag im Eingang (home). route() bindet die id
                // des Items an {item}; fehlt home → route() wirft → Entity/Show fängt
                // es ab → nicht klickbar (graceful).
                'route' => 'home.inbox',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        // Quelle mitladen — Meeting/Anruf brauchen Start/Ende bzw. Dauer für die Eckdaten.
        $query->with('source');
    }

    /**
     * Eckdaten pro Eintrag: Art, Datum, bei Termin/Anruf Uhrzeit + Dauer, bei einer
     * Aufnahme ihre Länge, plus Status („Offen"/„Erledigt"). Die Blade-Statuszeile
     * liest `status` (überschreibt hier den Roh-Status), `is_done` streicht durch.
     */
    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if (! $model instanceof InboxItem) {
            return [];
        }

        $channel = $model->channel;
        $received = $model->received_at;
        $isDone = $model->status === Status::Done;

        $clock = null;
        $durationMin = null;

        if ($channel === Channel::Meeting) {
            $source = $model->source;
            $start = $source?->start_at ? Carbon::parse($source->start_at) : $received;
            $end = $source?->end_at ? Carbon::parse($source->end_at) : null;
            $clock = $start?->format('H:i');
            if ($start && $end) {
                $durationMin = (int) max(0, $start->diffInMinutes($end));
            }
        } elseif ($channel === Channel::Call) {
            $source = $model->source;
            $start = $source?->started_at ? Carbon::parse($source->started_at) : $received;
            $clock = $start?->format('H:i');
            if ($source?->duration_seconds) {
                $durationMin = intdiv((int) $source->duration_seconds, 60);
            }
        } elseif ($channel === Channel::Recording) {
            if ($model->audio_duration_seconds) {
                $durationMin = intdiv((int) $model->audio_duration_seconds, 60);
            }
        }

        return array_filter([
            'art' => $channel?->label(),
            'date' => $received?->copy()->locale('de')->isoFormat('D. MMM'),
            'clock' => $clock,
            'duration_min' => $durationMin,
            'is_done' => $isDone,
            'status' => $isDone ? 'Erledigt' : 'Offen',
        ], fn ($v) => $v !== null);
    }

    public function metadataDisplayRules(): array
    {
        return [
            'inbox_item' => [
                ['field' => 'art', 'format' => 'badge'],
                ['field' => 'date', 'format' => 'text'],
                ['field' => 'clock', 'format' => 'text', 'suffix' => 'Uhr'],
                ['field' => 'duration_min', 'format' => 'time'],
            ],
        ];
    }

    /**
     * Inbox-Item trägt die Ist-Zeit direkt (keine Child-Relations): die Buchung
     * hängt am Item selbst (context_type = InboxItem, context_id = item_id).
     */
    public function timeTrackableCascades(): array
    {
        return [
            'inbox_item' => [InboxItem::class, []],
        ];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        return [];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }
}
