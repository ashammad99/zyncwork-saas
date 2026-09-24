import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { Pencil, Trash2, CornerDownRight, X } from "lucide-react";
import MentionTextarea, { renderBody } from "./MentionTextarea";

/**
 * @param {object} props
 * @param {object} props.comment
 * @param {number} props.taskId
 * @param {{ id: number, role: string }} props.currentUser
 * @param {Array<{ id, name }>} props.mentionableUsers
 * @param {number} props.depth
 */
export default function CommentItem({ comment, taskId, currentUser, mentionableUsers = [], depth = 0 }) {
    const [replying, setReplying] = useState(false);
    const [editing, setEditing] = useState(false);
    const [replyBody, setReplyBody] = useState("");
    const [editBody, setEditBody] = useState(comment.body);
    const [busy, setBusy] = useState(false);

    const isAuthor = comment.user_id === currentUser?.id;
    const withinEditWindow =
        Date.now() - new Date(comment.created_at).getTime() <= 15 * 60 * 1000;
    const canEdit = isAuthor && withinEditWindow;
    const canDelete = isAuthor || ["admin", "manager"].includes(currentUser?.role);

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
        <div style={{ marginLeft: depth > 0 ? 32 : 0 }}>
            <div className="flex items-start gap-3">
                {depth > 0 && (
                    <CornerDownRight size={14} className="text-gray-300 mt-2.5 shrink-0" />
                )}

                {/* Avatar */}
                <div className="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0 overflow-hidden">
                    {avatarUrl(comment.user, currentUser) ? (
                        <img
                            src={avatarUrl(comment.user, currentUser)}
                            className="w-full h-full object-cover"
                            alt=""
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
                            {comment.user?.name ?? "Unknown"}
                        </span>
                        <span className="text-xs text-gray-400">{formatWhen(comment.created_at)}</span>
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
                                <button type="button" onClick={saveEdit} disabled={busy}
                                        className="px-3 py-1 text-xs font-semibold bg-primary text-white rounded-lg disabled:opacity-50">
                                    Save
                                </button>
                                <button type="button"
                                        onClick={() => { setEditing(false); setEditBody(comment.body); }}
                                        className="px-3 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">
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
                            <button type="button" onClick={() => setReplying((v) => !v)}
                                    className="text-xs font-medium text-gray-500 hover:text-primary transition">
                                Reply
                            </button>
                            {canEdit && (
                                <button type="button" onClick={() => setEditing(true)}
                                        className="text-xs font-medium text-gray-500 hover:text-primary transition inline-flex items-center gap-1">
                                    <Pencil size={11} /> Edit
                                </button>
                            )}
                            {canDelete && (
                                <button type="button" onClick={remove}
                                        className="text-xs font-medium text-gray-500 hover:text-red-600 transition inline-flex items-center gap-1">
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
                                placeholder={`Reply to ${comment.user?.name ?? ""}…`}
                            />
                            <div className="flex gap-2 mt-1.5">
                                <button type="button" onClick={postReply} disabled={busy || !replyBody.trim()}
                                        className="px-3 py-1 text-xs font-semibold bg-primary text-white rounded-lg disabled:opacity-50">
                                    Reply
                                </button>
                                <button type="button" onClick={() => setReplying(false)}
                                        className="px-3 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 inline-flex items-center gap-1">
                                    <X size={11} /> Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Nested replies */}
                    {comment.replies?.length > 0 && (
                        <div className="mt-3 flex flex-col gap-3">
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
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

/**
 * Build avatar URL. The route prefix depends on the VIEWER's role
 * (their session must pass that route's middleware):
 *   admin → /admin/profile-image, everyone else → /user/profile-image
 */
function avatarUrl(user, viewer) {
    const filename = user?.profile_image ?? user?.avatar;
    if (!filename) return null;
    if (typeof filename === "string" && filename.startsWith("/")) return filename;

    const base = viewer?.role === "admin" ? "/admin/profile-image" : "/user/profile-image";
    return `${base}/${filename}`;
}

function formatWhen(iso) {
    const d = new Date(iso);
    const mins = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mins < 1) return "just now";
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    return d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}
