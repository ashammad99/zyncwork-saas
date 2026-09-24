import CommentBox from "./CommentBox";
import CommentItem from "./CommentItem";

export default function CommentThread({ taskId, comments = [], currentUser, mentionableUsers = [] }) {
    return (
        <section className="flex flex-col gap-4">
            <div className="flex items-center gap-2">
                <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                    Comments
                </h3>
                {comments.length > 0 && (
                    <span className="inline-flex items-center justify-center min-w-5 h-5 px-1.5 bg-gray-100 text-gray-500 rounded-full text-xs font-medium">
                        {comments.length}
                    </span>
                )}
            </div>

            <CommentBox taskId={taskId} mentionableUsers={mentionableUsers} />

            {comments.length === 0 ? (
                <p className="text-sm text-gray-400 text-center py-4">
                    No comments yet. Be the first to leave one.
                </p>
            ) : (
                <div className="flex flex-col gap-4">
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
                </div>
            )}
        </section>
    );
}
