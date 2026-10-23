<?php

namespace Platform\Core\Terminal\Apps;

use Illuminate\Support\Facades\DB;
use Platform\Core\Livewire\Terminal\Time;
use Platform\Core\Models\ContextFile;
use Platform\Core\Terminal\TerminalContext;

/** Zähler für die Core-Reiter im Kontext-Panel — je eine schlanke Abfrage. */
class ContextBadges
{
    public static function files(TerminalContext $context): ?string
    {
        $n = ContextFile::where('context_type', $context->contextType)
            ->where('context_id', $context->contextId)
            ->count();

        return static::count($n);
    }

    /** Team-Tags + eigene persönliche Tags (wie der Tags-Reiter). */
    public static function tags(TerminalContext $context): ?string
    {
        if (! class_exists($context->contextType)) {
            return null;
        }
        $userId = auth()->id();
        $n = DB::table('taggables')
            ->where('taggable_type', (new ($context->contextType))->getMorphClass())
            ->where('taggable_id', $context->contextId)
            ->where(fn ($q) => $userId
                ? $q->whereNull('user_id')->orWhere('user_id', $userId)
                : $q->whereNull('user_id'))
            ->count();

        return static::count($n);
    }

    /** Gebuchte Zeit inkl. Kind-Kontexte (z. B. Tasks eines Projekts), wie der Zeit-Reiter. */
    public static function time(TerminalContext $context): ?string
    {
        $entry = 'Platform\\Organization\\Models\\OrganizationTimeEntry';
        if (! class_exists($entry)) {
            return null;
        }

        $pairs = [$context->contextType => [$context->contextId]];
        foreach (Time::childContextPairs($context->contextType, $context->contextId) as $type => $ids) {
            $pairs[$type] = array_merge($pairs[$type] ?? [], $ids);
        }

        $minutes = (int) $entry::query()
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $type => $ids) {
                    $q->orWhere(fn ($sq) => $sq->where('context_type', $type)->whereIn('context_id', array_unique($ids)));
                }
            })
            ->sum('minutes');

        if ($minutes <= 0) {
            return null;
        }

        return $minutes < 60 ? $minutes . 'm' : round($minutes / 60) . 'h';
    }

    public static function count(int $n): ?string
    {
        return $n > 0 ? ($n > 99 ? '99+' : (string) $n) : null;
    }
}
