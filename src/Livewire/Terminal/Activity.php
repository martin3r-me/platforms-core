<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\TerminalMessage;

class Activity extends Component
{
    use WithFileUploads;
    use WithTerminalContext;

    public string $activityFilter = 'all';
    public $pendingFiles = [];

    protected function onContextChanged(): void
    {
        unset($this->contextActivities);
    }

    public function addActivityNote(string $text, ?string $_unused = null, array $attachmentIds = []): void
    {
        $plain = trim($text);
        if (empty($plain) && empty($attachmentIds)) {
            return;
        }

        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model || ! method_exists($model, 'logActivity')) {
            return;
        }

        $metadata = [];
        if (! empty($attachmentIds)) {
            $metadata['attachment_ids'] = $attachmentIds;
        }

        $model->logActivity($plain, $metadata);

        if (! empty($attachmentIds)) {
            $activity = $model->activities()->latest()->first();
            if ($activity) {
                ContextFile::whereIn('id', $attachmentIds)
                    ->where('context_type', TerminalMessage::class)
                    ->where('context_id', 0)
                    ->where('user_id', auth()->id())
                    ->update([
                        'context_type' => get_class($activity),
                        'context_id' => $activity->id,
                    ]);
            }
        }

        unset($this->contextActivities);
    }

    public function deleteActivityNote(int $activityId): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model || ! method_exists($model, 'activities')) {
            return;
        }

        $model->activities()
            ->where('id', $activityId)
            ->where('activity_type', 'manual')
            ->where('user_id', auth()->id())
            ->delete();

        unset($this->contextActivities);
    }

    public function uploadAttachments(): array
    {
        if (empty($this->pendingFiles)) {
            return [];
        }

        $results = [];
        $userId = auth()->id();

        foreach ($this->pendingFiles as $file) {
            $contextFile = \Platform\Core\Services\ContextFileService::store(
                $file,
                TerminalMessage::class,
                0,
                $userId,
                $this->teamId(),
            );

            $results[] = [
                'id' => $contextFile->id,
                'url' => $contextFile->url,
                'original_name' => $contextFile->original_name,
                'mime_type' => $contextFile->mime_type,
                'is_image' => $contextFile->isImage(),
            ];
        }

        $this->pendingFiles = [];

        return $results;
    }

    #[Computed]
    public function contextActivities(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        if (! class_exists($this->contextType)) {
            return [];
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model || ! method_exists($model, 'activities')) {
            return [];
        }

        $currentUserId = auth()->id();

        $activities = $model->activities()
            ->with('user')
            ->limit(30)
            ->get();

        $activityClass = get_class($activities->first() ?? new \stdClass());
        $activityIds = $activities->pluck('id')->toArray();
        $attachmentsByActivity = [];
        if (! empty($activityIds) && class_exists($activityClass)) {
            $attachmentsByActivity = ContextFile::where('context_type', $activityClass)
                ->whereIn('context_id', $activityIds)
                ->get()
                ->groupBy('context_id');
        }

        return $activities
            ->map(function ($activity) use ($currentUserId, $attachmentsByActivity) {
                $userName = $activity->user?->name ?? 'System';
                $event = $activity->name;
                $isManual = $activity->activity_type === 'manual';

                if ($isManual && $activity->message) {
                    $title = $activity->message;
                } elseif ($activity->message) {
                    $title = "{$userName}: {$activity->message}";
                } else {
                    $translations = [
                        'created' => 'erstellt',
                        'updated' => 'aktualisiert',
                        'deleted' => 'gelöscht',
                    ];
                    $translated = $translations[$event] ?? $event;

                    $changedFields = [];
                    $props = $activity->properties ?? [];
                    if (! empty($props)) {
                        $fieldKeys = isset($props['new']) ? array_keys($props['new']) : (isset($props['old']) ? [] : array_keys($props));
                        $fieldTranslations = [
                            'title' => 'Titel', 'description' => 'Beschreibung', 'due_date' => 'Fälligkeitsdatum',
                            'is_done' => 'Status', 'status' => 'Status', 'priority' => 'Priorität',
                            'name' => 'Name', 'user_in_charge_id' => 'Verantwortlicher',
                        ];
                        $changedFields = array_map(fn ($f) => $fieldTranslations[$f] ?? $f, $fieldKeys);
                    }

                    $title = $changedFields
                        ? "{$userName} hat " . implode(', ', array_slice($changedFields, 0, 3)) . " {$translated}"
                        : "{$userName} hat {$translated}";
                }

                $files = $attachmentsByActivity[$activity->id] ?? collect();
                $attachments = $files->map(fn (ContextFile $f) => [
                    'id' => $f->id,
                    'url' => $f->url,
                    'download_url' => $f->download_url,
                    'original_name' => $f->original_name,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                    'is_image' => $f->isImage(),
                ])->values()->toArray();

                return [
                    'id' => $activity->id,
                    'title' => $title,
                    'message' => $activity->message,
                    'user' => $userName,
                    'user_avatar' => $activity->user?->avatar,
                    'user_initials' => $this->initials($activity->user?->name ?? '?'),
                    'activity_type' => $activity->activity_type ?? 'system',
                    'is_mine' => $activity->user_id === $currentUserId,
                    'has_attachments' => ! empty($attachments),
                    'attachments' => $attachments,
                    'time' => $activity->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    protected function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }

        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }

    public function render()
    {
        return view('platform::livewire.terminal.activity');
    }
}
