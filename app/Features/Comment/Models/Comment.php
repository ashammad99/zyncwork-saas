<?php

namespace App\Features\Comment\Models;
use App\Features\Auth\Models\User;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'parent_id',
        'body',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->with('user');
    }

    // ─── Business Logic Helpers ───────────────────────────────────────────────

    /**
     * Whether this comment is still within the 15-minute edit window.
     */
    public function isEditable(): bool
    {
        return $this->created_at->gt(now()->subMinutes(15));
    }

    /**
     * Parse @mentions from body and return matched usernames.
     */
    /**
     * Parse mention tokens of the form @[Display Name](userId) from body.
     * Returns array of mentioned user IDs.
     */
    public function parseMentionIds(): array
    {
        preg_match_all('/@\[[^\]]+\]\((\d+)\)/', $this->body, $matches);

        return array_unique(array_map('intval', $matches[1] ?? []));
    }
}
