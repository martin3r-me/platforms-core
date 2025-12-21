<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Platform\Core\Models\Tag;
use Illuminate\Support\Facades\Auth;

trait HasTags
{
    /**
     * Alle Tags dieser Entity (Team + persönlich)
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables')
            ->withPivot('user_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Nur Team-Tags dieser Entity
     */
    public function teamTags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables')
            ->wherePivotNull('user_id')
            ->withPivot('user_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Nur persönliche Tags dieser Entity
     */
    public function personalTags(): MorphToMany
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->morphToMany(Tag::class, 'taggable', 'taggables')
                ->whereRaw('1 = 0'); // Leere Collection wenn nicht eingeloggt
        }

        return $this->morphToMany(Tag::class, 'taggable', 'taggables')
            ->wherePivot('user_id', $userId)
            ->withPivot('user_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Persönliche Tags eines bestimmten Users
     */
    public function personalTagsForUser(int $userId): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables')
            ->wherePivot('user_id', $userId)
            ->withPivot('user_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Tag zuordnen (Team oder persönlich)
     *
     * @param Tag|int|string $tag Tag-Objekt, ID oder Name
     * @param bool $personal true = persönlich, false = Team-Tag
     * @return void
     */
    public function tag($tag, bool $personal = false): void
    {
        $tagModel = $this->resolveTag($tag);
        if (!$tagModel) {
            return;
        }

        $userId = $personal ? Auth::id() : null;
        $teamId = $this->getTeamIdForTagging();

        // Prüfe ob Tag bereits zugeordnet ist
        $exists = $this->tags()
            ->where('tags.id', $tagModel->id)
            ->wherePivot('user_id', $userId)
            ->exists();

        if (!$exists) {
            $this->tags()->attach($tagModel->id, [
                'user_id' => $userId,
                'team_id' => $teamId,
            ]);
        }
    }

    /**
     * Tag entfernen
     *
     * @param Tag|int|string $tag Tag-Objekt, ID oder Name
     * @param bool $personal true = nur persönliche, false = nur Team-Tags, null = beide
     * @return void
     */
    public function untag($tag, ?bool $personal = null): void
    {
        $tagModel = $this->resolveTag($tag);
        if (!$tagModel) {
            return;
        }

        $query = $this->tags()->where('tags.id', $tagModel->id);

        if ($personal === true) {
            $query->wherePivot('user_id', Auth::id());
        } elseif ($personal === false) {
            $query->wherePivotNull('user_id');
        }

        $query->detach();
    }

    /**
     * Prüft ob Entity ein bestimmtes Tag hat
     *
     * @param Tag|int|string $tag Tag-Objekt, ID oder Name
     * @param bool|null $personal true = nur persönlich, false = nur Team, null = beide
     * @return bool
     */
    public function hasTag($tag, ?bool $personal = null): bool
    {
        $tagModel = $this->resolveTag($tag);
        if (!$tagModel) {
            return false;
        }

        $query = $this->tags()->where('tags.id', $tagModel->id);

        if ($personal === true) {
            $query->wherePivot('user_id', Auth::id());
        } elseif ($personal === false) {
            $query->wherePivotNull('user_id');
        }

        return $query->exists();
    }

    /**
     * Löst Tag aus verschiedenen Input-Formaten auf
     *
     * @param Tag|int|string $tag
     * @return Tag|null
     */
    protected function resolveTag($tag): ?Tag
    {
        if ($tag instanceof Tag) {
            return $tag;
        }

        if (is_int($tag)) {
            return Tag::find($tag);
        }

        if (is_string($tag)) {
            $teamId = $this->getTeamIdForTagging();
            return Tag::where('name', $tag)
                ->where(function ($q) use ($teamId) {
                    if ($teamId) {
                        $q->where('team_id', $teamId)->orWhereNull('team_id');
                    } else {
                        $q->whereNull('team_id');
                    }
                })
                ->first();
        }

        return null;
    }

    /**
     * Holt Team-ID für Tagging (Root-Team, nicht Child-Team)
     *
     * @return int|null
     */
    protected function getTeamIdForTagging(): ?int
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // Hole Root-Team (Parent-Team), nicht Child-Team
        $baseTeam = $user->currentTeamRelation;
        if (!$baseTeam) {
            return null;
        }

        $rootTeam = $baseTeam->getRootTeam();
        return $rootTeam?->id;
    }
}

