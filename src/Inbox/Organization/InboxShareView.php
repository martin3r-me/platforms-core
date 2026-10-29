<?php

namespace Platform\Core\Inbox\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Platform\Core\Inbox\Enums\Channel;
use Platform\Core\Inbox\Enums\InboxItemRelation;
use Platform\Core\Inbox\Models\InboxItem;
use Platform\Core\Inbox\Models\InboxItemLink;
use Platform\Core\Inbox\Models\InboxItemParticipant;
use Platform\Core\Models\User;
use Platform\Organization\Contracts\ProvidesShareView;

/**
 * Kunden-Tor · „Meetings": Termine am geteilten Knoten mit dem, was dabei herauskam.
 *
 * Nach außen geht bewusst wenig — und nur, was sicher ein Kundentermin ist:
 *   - nur Termine (channel=meeting) — Mails, Anrufe, Nachrichten nie, auch wenn sie am Knoten hängen;
 *   - nur mit mindestens einer Adresse außerhalb unserer Domains (rein interne Runden bleiben intern);
 *   - keine abgesagten/gelöschten Termine;
 *   - je Vorkommen EIN Meeting, obwohl jeder Teilnehmer bei uns ein eigenes Item hat
 *     (gleiche ical_uid + Tag);
 *   - von einer Aufnahme nur die Auswertung (Kurzfassung, Beschlüsse, offene Fragen, Aufgaben) —
 *     kein Transkript, kein Audio, keine E-Mail-Adressen.
 * Der Typ ist opt_in: er erscheint nur in Freigaben, die „Meetings" ausdrücklich einschließen.
 * Ausblenden eines Meetings in der Freigabe gilt für alle Items desselben Vorkommens.
 */
class InboxShareView implements ProvidesShareView
{
    private const TZ = 'Europe/Berlin';

    /** @var array<string, array> Ergebnis je Modell-Satz (eine Seite ruft Items, Summary, Activity, Section) */
    private array $memo = [];

    private ?array $internalDomains = null;

    public function shareTypes(): array
    {
        return [
            'inbox_item' => ['label' => 'Meetings', 'singular' => 'Meeting', 'icon' => 'calendar-days', 'slug' => 'meetings',
                'group' => 'arbeit', 'nav' => 'section', 'order' => 30, 'opt_in' => true],
        ];
    }

    public function shareItems(string $alias, Collection $models, array $options): array
    {
        return collect($this->meetings($models, $options))->map(fn ($m) => [
            'id' => $m['id'],
            'title' => $m['title'],
            'subtitle' => $m['when'],
            'state' => $m['upcoming'] ? 'planned' : 'done',
            'state_label' => $m['upcoming'] ? 'Geplant' : 'Stattgefunden',
            'due' => $m['upcoming'] ? $m['start'] : null,
            'updated_at' => $m['start'],
        ])->values()->all();
    }

    public function shareSummary(string $alias, Collection $models, array $options): ?array
    {
        $meetings = collect($this->meetings($models, $options));
        if ($meetings->isEmpty()) {
            return null;
        }
        $next = $meetings->where('upcoming', true)->sortBy(fn ($m) => $m['start']->timestamp)->first();

        return [
            'title' => 'Meetings',
            'status' => $next ? ['text' => 'Nächstes ' . $next['start']->copy()->locale('de')->isoFormat('D. MMM'), 'tone' => 'info'] : null,
            'kpis' => [],
        ];
    }

    public function shareActivity(string $alias, Collection $models, array $options): array
    {
        $from = now()->subDays(60);

        return collect($this->meetings($models, $options))
            ->filter(fn ($m) => ! $m['upcoming'] && $m['start']->gte($from))
            ->map(fn ($m) => [
                'at' => $m['end'] ?? $m['start'], 'kind' => 'meeting',
                'title' => 'Meeting: ' . $m['title'],
                'text' => $m['outcome']['tldr'] ?? null,
                'parent_id' => null,
            ])->values()->all();
    }

    public function shareSection(string $alias, Collection $models, array $options): ?array
    {
        if (($options['sub'] ?? null) !== null) {
            return null;
        }
        $meetings = collect($this->meetings($models, $options));
        if ($meetings->isEmpty()) {
            return null;
        }
        $upcoming = $meetings->where('upcoming', true)->sortBy(fn ($m) => $m['start']->timestamp)->values();
        $past = $meetings->where('upcoming', false)->sortByDesc(fn ($m) => $m['start']->timestamp)->values();

        return [
            'title' => 'Meetings',
            'subtitle' => null,
            'description' => 'Unsere gemeinsamen Termine — was ansteht und was dabei herauskam.',
            'state' => 'active',
            'state_label' => $upcoming->isNotEmpty() ? $upcoming->count() . ' geplant' : 'Keine geplant',
            'progress' => null,
            'facts' => array_values(array_filter([
                ['label' => 'Nächstes Meeting', 'value' => $upcoming->isNotEmpty() ? $upcoming->first()['start']->copy()->locale('de')->isoFormat('dd, D. MMM · HH:mm') . ' Uhr' : '—'],
                ['label' => 'Stattgefunden', 'value' => (string) $past->count()],
                $past->where('recorded', true)->isNotEmpty() ? ['label' => 'Mit Zusammenfassung', 'value' => (string) $past->filter(fn ($m) => $m['outcome'])->count()] : null,
            ])),
            'tabs' => [[
                'key' => 'termine', 'label' => 'Termine', 'count' => null,
                'view' => 'platform::share.meetings',
                'data' => ['upcoming' => $upcoming->all(), 'past' => $past->all()],
            ]],
        ];
    }

    // ── Daten ──────────────────────────────────────────────────

    /** Kundentaugliche Meetings aus den sichtbaren Items, je Vorkommen eins. */
    protected function meetings(Collection $models, array $options): array
    {
        $hidden = array_map('intval', (array) (($options['hidden'] ?? [])['inbox_item'] ?? []));
        $key = md5($models->pluck('id')->sort()->implode(',') . '|' . implode(',', $hidden));

        return $this->memo[$key] ??= $this->build($models, $hidden);
    }

    protected function build(Collection $models, array $hidden): array
    {
        $items = $models->filter(fn ($m) => $m instanceof InboxItem && $m->channel === Channel::Meeting);
        if ($items->isEmpty()) {
            return [];
        }
        $items->load(['source', 'participants']);
        $now = now();
        $out = [];

        foreach ($items->groupBy(fn (InboxItem $i) => $this->occurrenceKey($i)) as $group) {
            $start = $group->map(fn ($i) => $this->time($i, 'start_at') ?? $i->received_at)->filter()->min();
            if (! $start) {
                continue;
            }
            // Abgesagt/gelöscht → raus
            if ($group->contains(fn ($i) => in_array($i->source?->status ?? null, ['cancelled', 'deleted'], true))) {
                continue;
            }
            $siblings = $this->siblingIds($group, $start);
            if (array_intersect($siblings, $hidden)) {
                continue;
            }
            $emails = $this->emails($group);
            if (! $this->hasExternal($emails)) {
                continue;
            }

            $end = $group->map(fn ($i) => $this->time($i, 'end_at'))->filter()->max();
            $first = $group->sortBy('id')->first();
            $source = $group->map->source->filter()->first();
            $online = (bool) ($source?->is_online_meeting ?? false);
            $location = trim((string) ($source?->location ?? ''));
            [$outcome, $recordedMinutes] = $this->outcome($siblings, $group);
            $startLocal = $start->copy()->setTimezone(self::TZ);
            $endLocal = $end?->copy()->setTimezone(self::TZ);

            $out[] = [
                'id' => $first->id,
                'title' => trim((string) ($source?->subject ?: $first->subject)) ?: 'Meeting',
                'start' => $startLocal,
                'end' => $endLocal,
                'when' => $startLocal->copy()->locale('de')->isoFormat('dd, D. MMM YYYY · HH:mm') . ($endLocal ? '–' . $endLocal->format('H:i') : '') . ' Uhr',
                'minutes' => $endLocal ? (int) $startLocal->diffInMinutes($endLocal) : null,
                'upcoming' => $start->gt($now),
                'place' => $online ? 'Online' : ($location !== '' && ! str_contains($location, '@') ? $location : null),
                'organizer' => $source?->organizer_name ?: $group->flatMap->participants->firstWhere('role', InboxItemParticipant::ROLE_ORGANIZER)?->display_name,
                'participants' => count($emails),
                'recorded' => $recordedMinutes !== null,
                'recorded_minutes' => $recordedMinutes,
                'outcome' => $outcome,
            ];
        }

        return $out;
    }

    /** Ein Vorkommen = gleiche ical_uid am gleichen Tag; ohne ical_uid Organisator + Betreff + Beginn. */
    protected function occurrenceKey(InboxItem $i): string
    {
        $start = $this->time($i, 'start_at') ?? $i->received_at;
        $day = $start?->copy()->setTimezone(self::TZ)->format('Y-m-d') ?? '';

        return $i->ical_uid
            ? 'uid|' . $i->ical_uid . '|' . $day
            : 'raw|' . mb_strtolower((string) $i->sender_identifier) . '|' . mb_strtolower(trim((string) $i->subject)) . '|' . ($start?->timestamp ?? $i->id);
    }

    /** Alle Items desselben Vorkommens im Team — für Aufnahmen und fürs Ausblenden. */
    protected function siblingIds(Collection $group, Carbon $start): array
    {
        $ids = $group->pluck('id')->all();
        $uid = $group->first()->ical_uid;
        if (! $uid) {
            return $ids;
        }
        $day = $start->copy()->setTimezone(self::TZ);

        return InboxItem::where('channel', Channel::Meeting->value)
            ->whereIn('team_id', $group->pluck('team_id')->unique()->all())
            ->where('ical_uid', $uid)
            ->whereBetween('received_at', [$day->copy()->startOfDay()->subHours(14), $day->copy()->endOfDay()->addHours(14)])
            ->pluck('id')->merge($ids)->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Teilnehmer-Adressen (Organisator + Eingeladene), klein geschrieben. */
    protected function emails(Collection $group): array
    {
        $emails = [];
        foreach ($group as $i) {
            foreach ($i->participants as $p) {
                if (in_array($p->role, [InboxItemParticipant::ROLE_ORGANIZER, InboxItemParticipant::ROLE_ATTENDEE], true)) {
                    $emails[] = $p->identifier;
                }
            }
            $emails[] = $i->source?->organizer_address;
            foreach (explode(',', (string) ($i->source?->attendee_addresses ?? '')) as $a) {
                $emails[] = $a;
            }
        }

        return collect($emails)->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter(fn ($e) => str_contains($e, '@'))->unique()->values()->all();
    }

    /** Mindestens eine Adresse außerhalb unserer Domains (Domains aller Nutzer der Instanz). */
    protected function hasExternal(array $emails): bool
    {
        $this->internalDomains ??= User::query()->whereNotNull('email')->pluck('email')
            ->map(fn ($e) => mb_strtolower(substr(strrchr((string) $e, '@') ?: '', 1)))
            ->filter()->unique()->flip()->all();

        foreach ($emails as $e) {
            if (! isset($this->internalDomains[substr(strrchr($e, '@'), 1)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Was herauskam: Auswertung der Aufnahme zu diesem Vorkommen (sonst die des Termins).
     *
     * @return array{0: ?array, 1: ?int} [outcome, Aufnahme-Minuten]
     */
    protected function outcome(array $siblings, Collection $group): array
    {
        $recordingIds = InboxItemLink::whereIn('to_inbox_item_id', $siblings)
            ->where('relation', InboxItemRelation::Supplements->value)
            ->pluck('from_inbox_item_id');
        $recordings = $recordingIds->isNotEmpty()
            ? InboxItem::whereIn('id', $recordingIds)->where('channel', Channel::Recording->value)->orderBy('id')->get()
            : collect();
        $minutes = $recordings->isNotEmpty() ? (int) round($recordings->sum('audio_duration_seconds') / 60) : null;

        foreach ($recordings->merge($group) as $item) {
            $out = (array) ($item->primaryEnrichment()?->output ?? []);
            $outcome = array_filter([
                'tldr' => $this->text($out['tldr'] ?? null),
                'summary' => $this->text($out['summary'] ?? null),
                'decisions' => $this->lines($out['decisions'] ?? []),
                'open_questions' => $this->lines($out['open_questions'] ?? []),
                'action_items' => $this->lines($out['action_items'] ?? []),
            ]);
            if ($outcome) {
                return [$outcome, $minutes];
            }
        }

        return [null, $minutes];
    }

    protected function time(InboxItem $i, string $field): ?Carbon
    {
        $v = $i->source?->{$field} ?? null;

        return $v ? Carbon::parse($v) : null;
    }

    protected function text($v): ?string
    {
        $v = is_string($v) ? trim(strip_tags($v)) : null;

        return $v !== '' ? $v : null;
    }

    /** Liste aus Strings oder Objekten mit text/title — nur der Text, ohne Zuständige. */
    protected function lines($v): array
    {
        return collect(is_array($v) ? $v : [])
            ->map(fn ($x) => $this->text(is_array($x) ? ($x['text'] ?? $x['title'] ?? null) : $x))
            ->filter()->values()->all();
    }
}
