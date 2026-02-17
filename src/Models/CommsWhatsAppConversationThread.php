<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommsWhatsAppConversationThread extends Model
{
    protected $table = 'comms_whatsapp_conversation_threads';

    protected $fillable = [
        'uuid',
        'comms_whatsapp_thread_id',
        'team_id',
        'label',
        'started_at',
        'ended_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->started_at)) {
                $model->started_at = now();
            }
        });
    }

    public function whatsappThread(): BelongsTo
    {
        return $this->belongsTo(CommsWhatsAppThread::class, 'comms_whatsapp_thread_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommsWhatsAppMessage::class, 'conversation_thread_id');
    }

    /**
     * Check if this conversation thread is still active (not ended).
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Close this conversation thread by setting ended_at.
     */
    public function close(): void
    {
        if ($this->isActive()) {
            $this->update(['ended_at' => now()]);
        }
    }

    /**
     * Find the currently active conversation thread for a given WhatsApp thread.
     */
    public static function findActiveForThread(int $whatsappThreadId): ?self
    {
        return static::query()
            ->where('comms_whatsapp_thread_id', $whatsappThreadId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * Start a new conversation thread, closing any active one first.
     */
    public static function startNew(
        int $whatsappThreadId,
        int $teamId,
        string $label,
        ?int $createdByUserId = null,
    ): self {
        // Close any currently active thread
        static::query()
            ->where('comms_whatsapp_thread_id', $whatsappThreadId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        return static::create([
            'comms_whatsapp_thread_id' => $whatsappThreadId,
            'team_id' => $teamId,
            'label' => $label,
            'started_at' => now(),
            'created_by_user_id' => $createdByUserId,
        ]);
    }
}
