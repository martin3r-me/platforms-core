<?php

namespace Platform\Core\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Platform\Core\Inbox\Enums\Channel;
use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Das Kern-Model der Inbox-Engine (Core) — bewusst SCHLANK: nur der Triage-Kern
 * (Identität, Quelle, Kanal, Zustand, Gruppierung, Content-Cache, Score). KEINE
 * Relations zu Enrichment/Segments/Participants — das sind Satellit-/Agenten-
 * Belange, die nicht im Kern leben.
 *
 * Nutzt dieselbe Tabelle `inbox_items`. Während des Umbaus koexistiert es mit dem
 * alten \Platform\Inbox\Models\InboxItem (gleiche Tabelle, gleiche Zeilen); der
 * Kern schreibt über dieses Model, das inbox-Modul liest vorerst über seines.
 */
class InboxItem extends Model
{
    protected $table = 'inbox_items';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'source_type',
        'source_id',
        // Gruppierung (Thread/Serie/Meeting) — Kern, weil der Stream danach kollabiert
        'series_master_id',
        'ical_uid',
        'occurrence_type',
        'conversation_id',
        'meeting_id',
        'thread_key',
        // Kanal + Absender-Cache
        'channel',
        'sender_identifier',
        'sender_kind',
        'sender_label',
        // Content-Cache
        'subject',
        'preview',
        'body',
        'body_format',
        'direction',
        // Triage-Zustand
        'status',
        'snoozed_until',
        'handled_at',
        'received_at',
        // Signal
        'importance_score',
        'importance_scored_at',
        'awaiting_reply_since',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'status' => Status::class,
        'snoozed_until' => 'datetime',
        'handled_at' => 'datetime',
        'received_at' => 'datetime',
        'importance_score' => 'decimal:2',
        'importance_scored_at' => 'datetime',
        'awaiting_reply_since' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Polymorphe Quelle (Ticket, Task, Connector-Session, …). */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', Status::New->value);
    }

    public function scopeNotSnoozed($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
        });
    }
}
