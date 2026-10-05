<?php

namespace App\Features\Comment\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\Comment\Models\Comment;
use App\Features\Notification\Events\NotificationCreated;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class CommentService extends BaseService
{
    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Task $task, array $data, User $actor): Comment
    {
        /** @var Comment $comment */
        $comment = $task->comments()->create([
            'user_id' => $actor->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);
        //
        //        $this->activityLog->log(
        //            actor: $actor,
        //            action: 'created_comment',
        //            model: $comment,
        //        );

        $this->dispatchMentionNotifications($comment, $actor);

        return $comment->load('user');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Comment $comment, array $data, User $actor): Comment
    {
        $oldBody = $comment->body;

        $comment->update([
            'body' => $data['body'],
            'edited_at' => now(),
        ]);

        //        $this->activityLog->log(
        //            actor: $actor,
        //            action: 'updated_comment',
        //            model: $comment,
        //            oldValue: ['body' => $oldBody],
        //            newValue: ['body' => $data['body']],
        //        );

        $this->dispatchMentionNotifications($comment, $actor);

        return $comment->fresh('user');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Comment $comment, User $actor): void
    {
        //        $this->activityLog->log(
        //            actor: $actor,
        //            action: 'deleted_comment',
        //            model: $comment,
        //            oldValue: ['body' => $comment->body],
        //        );

        $comment->delete();
    }

    // ─── Fetch thread ─────────────────────────────────────────────────────────

    /**
     * Returns top-level comments with nested replies (2 levels deep is sufficient for M2).
     */
    public function getThreadForTask(Task $task): Collection
    {
        return $task->comments()
            ->with([
                'user:id,name,profile_image',
                'replies.user:id,name,profile_image',
                'replies.replies.user:id,name,profile_image',
            ])
            ->whereNull('parent_id')
            ->latest()
            ->get();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────
    private function dispatchMentionNotifications(Comment $comment, User $actor): void
    {
        $mentionedIds = $comment->parseMentionIds();

        if (empty($mentionedIds)) {
            return;
        }

        $mentionedUsers = User::whereIn('id', $mentionedIds)
            ->where('id', '!=', $actor->id)
            ->where('is_active', true)
            ->get();

        $preview = str($this->stripMentionTokens($comment->body))->limit(150)->toString();

        foreach ($mentionedUsers as $user) {
            event(new NotificationCreated(
                $user,
                "{$actor->name} mentioned you in a comment",
                $preview,
                'mention',
            ));
        }
    }

    /** Turn @[Sami Williams](13) into @Sami Williams for display */
    private function stripMentionTokens(string $body): string
    {
        return preg_replace('/@\[([^\]]+)\]\(\d+\)/', '@$1', $body);
    }
}
