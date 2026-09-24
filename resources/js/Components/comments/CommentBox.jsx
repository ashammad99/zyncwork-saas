import { useState } from "react";
import { router } from "@inertiajs/react";
import MentionTextarea from "./MentionTextarea";

export default function CommentBox({ taskId, mentionableUsers = [] }) {
    const [body, setBody] = useState("");
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const submit = () => {
        if (!body.trim()) return;
        setErrors({});
        setProcessing(true);
        router.post(`/tasks/${taskId}/comments`, { body, parent_id: null }, {
            preserveScroll: true,
            onSuccess: () => setBody(""),
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="flex flex-col gap-2 mb-5">
            <MentionTextarea
                value={body}
                onChange={setBody}
                users={mentionableUsers}
                placeholder="Leave a comment… type @ to mention someone"
                rows={3}
            />
            {errors.body && (
                <p className="text-xs text-red-600">{errors.body}</p>
            )}
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs text-gray-400">
                    Markdown supported · type @ to mention teammates
                </span>
                <button
                    type="button"
                    onClick={submit}
                    disabled={processing || !body.trim()}
                    className="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 disabled:opacity-50 transition"
                >
                    {processing ? "Posting…" : "Post Comment"}
                </button>
            </div>
        </div>
    );
}
