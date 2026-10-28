<?php

namespace Platform\Core\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Platform\Core\Inbox\Enums\Channel;
use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Traits\HasContextFileReferences;
use Symfony\Component\Uid\UuidV7;

/**
 * Das Kern-Model der Inbox-Engine (Core).
 *
 * Trägt den Triage-Kern (Identität, Quelle, Kanal, Zustand, Gruppierung,
 * Content-Cache, Score) UND die Satelliten-Relations (Enrichment/Participants/
 * Segments), seit die Inbox-Engine vollständig in Core lebt. Nutzt die Tabelle
 * `inbox_items`. Das frühere Modul-Model (ehemals im inbox-Modul) ist aufgelöst —
 * dies ist das einzige, kanonische InboxItem.
 */
class InboxItem extends Model
{
    use HasContextFileReferences;

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
        'language',
        'audio_duration_seconds',
        'audio_recorded_at',
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
        'audio_recorded_at' => 'datetime',
        'audio_duration_seconds' => 'integer',
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

    public function enrichments(): HasMany
    {
        return $this->hasMany(InboxItemEnrichment::class, 'inbox_item_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InboxItemParticipant::class, 'inbox_item_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(InboxItemSegment::class, 'inbox_item_id')->orderBy('start_seconds');
    }

    public function primaryEnrichment(): ?InboxItemEnrichment
    {
        return $this->enrichments()
            ->where('is_primary', true)
            ->where('status', InboxItemEnrichment::STATUS_DONE)
            ->latest()
            ->first();
    }

    /**
     * Folder convention for any audio attached to this item — kept stable so
     * lifecycle policies (cleanup, archival) can act on path prefixes.
     */
    public function audioFolder(): string
    {
        $year = ($this->received_at ?? now())->format('Y');
        $month = ($this->received_at ?? now())->format('m');
        return "inbox/audio/{$this->team_id}/{$year}/{$month}";
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
