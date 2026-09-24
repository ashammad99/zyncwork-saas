import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { MessageSquare, Trash2, Pencil, CornerDownRight, X } from "lucide-react";
import MentionTextarea, { renderBody } from "../../../../Components/comments/MentionTextarea";


/**
 * @param {object} props
 * @param {number} props.taskId
 * @param {Array<object>} props.comments        Top-level comments with nested replies
 * @param {{ id: number, role: string }} props.currentUser
 * @param {Array<{ id, name }>} props.mentionableUsers
 */
export default function CommentsSection({ taskId, comments = [], currentUser, mentionableUsers = [] }) {
    const [body, setBody] = useState("");
    const [posting, setPosting] = useState(false);

    const submit = () => {
        if (!body.trim()) return;
        setPosting(true);
        router.post(`/tasks/${taskId}/comments`, { body }, {
            preserveScroll: true,
            onSuccess: () => setBody(""),
            onFinish: () => setPosting(false),
        });
    };

    return (
        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm">
            <div className="px-6 py-4 flex items-center gap-2 border-b border-gray-100">
                <MessageSquare size={16} className="text-gray-400" />
                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">
                    Comments
                </h3>
                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                    {countAll(comments)}
                </span>
            </div>

            {/* New comment */}
            <div className="p-6 border-b border-gray-100">
                <MentionTextarea
                    value={body}
                    onChange={setBody}
                    users={mentionableUsers}
                />
                <div className="flex items-center justify-between mt-2">
                    <span className="text-xs text-gray-400">
                        Type @ to mention a teammate
                    </span>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={posting || !body.trim()}
                        className="px-4 py-2 text-sm font-semibold bg-primary text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                    >
                        {posting ? "Posting…" : "Post Comment"}
                    </button>
                </div>
            </div>

            {/* Thread */}
            {comments.length === 0 ? (
                <div className="px-6 py-10 text-center text-sm text-gray-400">
                    No comments yet. Start the conversation.
                </div>
            ) : (
                <ul className="divide-y divide-gray-50">
                    {comments.map((comment) => (
                        <CommentItem
                            key={comment.id}
                            comment={comment}
                            taskId={taskId}
                            currentUser={currentUser}
                            mentionableUsers={mentionableUsers}
                            depth={0}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}

/* ─── Single comment (recursive for replies) ─────────────── */

function CommentItem({ comment, taskId, currentUser, mentionableUsers, depth }) {
    const [replying, setReplying] = useState(false);
    const [editing, setEditing] = useState(false);
    const [replyBody, setReplyBody] = useState("");
    const [editBody, setEditBody] = useState(comment.body);
    const [busy, setBusy] = useState(false);

    const isAuthor = comment.user_id === currentUser.id;
    const withinEditWindow =
        Date.now() - new Date(comment.created_at).getTime() <= 15 * 60 * 1000;
    const canEdit = isAuthor && withinEditWindow;
    const canDelete = isAuthor || ["admin", "manager"].includes(currentUser.role);

    const postReply = () => {
        if (!replyBody.trim()) return;
        setBusy(true);
        router.post(`/tasks/${taskId}/comments`, { body: replyBody, parent_id: comment.id }, {
            preserveScroll: true,
            onSuccess: () => { setReplyBody(""); setReplying(false); },
            onFinish: () => setBusy(false),
        });
    };

    const saveEdit = () => {
        if (!editBody.trim()) return;
        setBusy(true);
        router.put(`/comments/${comment.id}`, { body: editBody }, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
            onFinish: () => setBusy(false),
        });
    };

    const remove = () => {
        if (!confirm("Delete this comment?")) return;
        router.delete(`/comments/${comment.id}`, { preserveScroll: true });
    };

    return (
        <li className={depth > 0 ? "" : ""}>
            <div className="px-6 py-4" style={{ paddingLeft: `${24 + depth * 36}px` }}>
                <div className="flex items-start gap-3">
                    {depth > 0 && (
                        <CornerDownRight size={14} className="text-gray-300 mt-2 shrink-0" />
                    )}
                    <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0 overflow-hidden">
                        {comment.user?.profile_image ? (
                            <img
                                src={`/user/profile-image/${comment.user.profile_image}`}
                                alt=""
                                className="w-full h-full object-cover"
                                onError={(e) => { e.target.style.display = "none"; }}
                            />
                        ) : (
                            comment.user?.name?.charAt(0) ?? "?"
                        )}
                    </div>

                    <div className="flex-1 min-w-0">
                        {/* Header */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-sm font-semibold text-gray-900">
                                {comment.user?.name}
                            </span>
                            <span className="text-xs text-gray-400">
                                {formatWhen(comment.created_at)}
                            </span>
                            {comment.edited_at && (
                                <span className="text-[10px] italic text-gray-400">(edited)</span>
                            )}
                        </div>

                        {/* Body / edit form */}
                        {editing ? (
                            <div className="mt-2">
                                <MentionTextarea
                                    value={editBody}
                                    onChange={setEditBody}
                                    users={mentionableUsers}
                                    rows={2}
                                />
                                <div className="flex gap-2 mt-1.5">
                                    <button
                                        type="button"
                                        onClick={saveEdit}
                                        disabled={busy}
                                        className="px-3 py-1 text-xs font-semibold bg-primary text-white rounded-lg disabled:opacity-50"
                                    >
                                        Save
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setEditing(false); setEditBody(comment.body); }}
                                        className="px-3 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <p className="mt-1 text-sm text-gray-700 whitespace-pre-wrap break-words">
                                {renderBody(comment.body)}
                            </p>
                        )}

                        {/* Actions */}
                        {!editing && (
                            <div className="flex items-center gap-3 mt-1.5">
                                <button
                                    type="button"
                                    onClick={() => setReplying((v) => !v)}
                                    className="text-xs font-medium text-gray-500 hover:text-primary transition"
                                >
                                    Reply
                                </button>
                                {canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setEditing(true)}
                                        className="text-xs font-medium text-gray-500 hover:text-primary transition inline-flex items-center gap-1"
                                    >
                                        <Pencil size={11} /> Edit
                                    </button>
                                )}
                                {canDelete && (
                                    <button
                                        type="button"
                                        onClick={remove}
                                        className="text-xs font-medium text-gray-500 hover:text-red-600 transition inline-flex items-center gap-1"
                                    >
                                        <Trash2 size={11} /> Delete
                                    </button>
                                )}
                            </div>
                        )}

                        {/* Reply form */}
                        {replying && (
                            <div className="mt-3">
                                <MentionTextarea
                                    value={replyBody}
                                    onChange={setReplyBody}
                                    users={mentionableUsers}
                                    rows={2}
                                    placeholder={`Reply to ${comment.user?.name}…`}
                                />
                                <div className="flex gap-2 mt-1.5">
                                    <button
                                        type="button"
                                        onClick={postReply}
                                        disabled={busy || !replyBody.trim()}
                                        className="px-3 py-1 text-xs font-semibold bg-primary text-white rounded-lg disabled:opacity-50"
                                    >
                                        Reply
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setReplying(false)}
                                        className="px-3 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 inline-flex items-center gap-1"
                                    >
                                        <X size={11} /> Cancel
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Nested replies */}
            {comment.replies?.length > 0 && (
                <ul>
                    {comment.replies.map((reply) => (
                        <CommentItem
                            key={reply.id}
                            comment={reply}
                            taskId={taskId}
                            currentUser={currentUser}
                            mentionableUsers={mentionableUsers}
                            depth={Math.min(depth + 1, 3)}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function countAll(comments) {
    let n = 0;
    const walk = (list) => {
        for (const c of list) {
            n++;
            if (c.replies?.length) walk(c.replies);
        }
    };
    walk(comments);
    return n;
}

function formatWhen(iso) {
    const d = new Date(iso);
    const diffMs = Date.now() - d.getTime();
    const mins = Math.floor(diffMs / 60000);
    if (mins < 1) return "just now";
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    return d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}
