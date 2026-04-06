<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Events\TerminalReminderDue;
use Platform\Core\Models\TerminalReminder;

class ProcessTerminalRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 1;

    public function handle(): void
    {
        $reminders = TerminalReminder::where('reminded', false)
            ->where('remind_at', '<=', now())
            ->with(['message:id,channel_id,body_plain', 'user:id,name'])
            ->limit(100)
            ->get();

        foreach ($reminders as $reminder) {
            $message = $reminder->message;
            if (! $message) {
                $reminder->update(['reminded' => true]);
                continue;
            }

            $snippet = \Illuminate\Support\Str::limit($message->body_plain ?? '', 80);

            try {
                TerminalReminderDue::dispatch(
                    $reminder->user_id,
                    $message->id,
                    $message->channel_id,
                    $snippet,
                );
            } catch (\Throwable $e) {
                Log::warning('Terminal reminder broadcast failed: ' . $e->getMessage());
            }

            $reminder->update(['reminded' => true]);
        }
    }
}
