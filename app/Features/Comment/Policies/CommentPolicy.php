<?php

namespace App\Features\Comment\Policies;

use App\Features\Auth\Models\User;
use App\Features\Comment\Models\Comment;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    /**
     * Any authenticated user who is a project collaborator may view comments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Any project collaborator can create a comment.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the comment author may update, and only within 15 minutes of posting.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id && $comment->isEditable();
    }

    /**
     * Author can delete their own comment; managers can delete comments
     * on tasks within their department; admins can delete any.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($user->id === $comment->user_id) {
            return true;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'manager') {
            return $comment->task->project->department_id === $user->department_id;
        }

        return false;
    }
}
