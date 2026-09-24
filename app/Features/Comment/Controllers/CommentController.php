<?php

namespace App\Features\Comment\Controllers;

use App\Core\BaseController;
use App\Features\Comment\Models\Comment;
use App\Features\Comment\Requests\StoreCommentRequest;
use App\Features\Comment\Requests\UpdateCommentRequest;
use App\Features\Comment\Services\CommentService;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Http\RedirectResponse;

class CommentController extends BaseController
{
    public function __construct(
        private readonly CommentService $service,
    ) {}

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreCommentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('create', Comment::class);

        $this->service->store($task, $request->validated(), $request->user());

        return back()->with('success', 'Comment posted.');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);

        $this->service->update($comment, $request->validated(), $request->user());

        return back()->with('success', 'Comment updated.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $this->service->destroy($comment, auth()->user());

        return back()->with('success', 'Comment deleted.');
    }
}
