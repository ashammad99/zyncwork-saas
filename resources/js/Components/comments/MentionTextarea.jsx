import React, { useState, useRef, useMemo } from "react";

/**
 * Textarea with @mention autocomplete.
 * Stores raw tokens `@[Name](id)` in `value`; shows a friendly overlay preview.
 *
 * @param {object} props
 * @param {string} props.value                       Raw body (with tokens)
 * @param {(v: string) => void} props.onChange
 * @param {Array<{ id: number, name: string }>} props.users  Mentionable users
 * @param {string} [props.placeholder]
 * @param {number} [props.rows]
 */
export default function MentionTextarea({
                                            value,
                                            onChange,
                                            users = [],
                                            placeholder = "Leave a comment… type @ to mention someone",
                                            rows = 3,
                                        }) {
    const textareaRef = useRef(null);
    const [mentionQuery, setMentionQuery] = useState(null); // null = dropdown closed
    const [highlightIdx, setHighlightIdx] = useState(0);

    // ── Detect an in-progress "@query" at the caret ────────────────────────
    const handleChange = (e) => {
        const text = e.target.value;
        onChange(text);

        const caret = e.target.selectionStart;
        const upToCaret = text.slice(0, caret);
        // Match "@word..." right before the caret, not already inside a token
        const match = upToCaret.match(/(^|[\s(])@(\w[\w ]*)?$/);

        if (match) {
            setMentionQuery(match[2] ?? "");
            setHighlightIdx(0);
        } else {
            setMentionQuery(null);
        }
    };

    const suggestions = useMemo(() => {
        if (mentionQuery === null) return [];
        const q = mentionQuery.toLowerCase();
        return users
            .filter((u) => u.name.toLowerCase().includes(q))
            .slice(0, 6);
    }, [mentionQuery, users]);

    // ── Insert the token at the caret ──────────────────────────────────────
    const pickUser = (user) => {
        const el = textareaRef.current;
        const caret = el.selectionStart;
        const upToCaret = value.slice(0, caret);
        const after = value.slice(caret);

        // Replace the trailing "@query" with the token
        const replaced = upToCaret.replace(/@(\w[\w ]*)?$/, `@[${user.name}](${user.id}) `);
        const next = replaced + after;

        onChange(next);
        setMentionQuery(null);

        // Restore focus + caret after React re-render
        requestAnimationFrame(() => {
            el.focus();
            el.selectionStart = el.selectionEnd = replaced.length;
        });
    };

    const handleKeyDown = (e) => {
        if (mentionQuery === null || suggestions.length === 0) return;

        if (e.key === "ArrowDown") {
            e.preventDefault();
            setHighlightIdx((i) => (i + 1) % suggestions.length);
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            setHighlightIdx((i) => (i - 1 + suggestions.length) % suggestions.length);
        } else if (e.key === "Enter" || e.key === "Tab") {
            e.preventDefault();
            pickUser(suggestions[highlightIdx]);
        } else if (e.key === "Escape") {
            setMentionQuery(null);
        }
    };

    return (
        <div className="relative">
            <textarea
                ref={textareaRef}
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                onBlur={() => setTimeout(() => setMentionQuery(null), 150)}
                rows={rows}
                placeholder={placeholder}
                className="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition resize-none"
            />

            {/* Suggestion dropdown */}
            {mentionQuery !== null && suggestions.length > 0 && (
                <ul className="absolute left-4 bottom-full mb-1 w-64 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden z-30">
                    {suggestions.map((user, i) => (
                        <li key={user.id}>
                            <button
                                type="button"
                                onMouseDown={(e) => {
                                    e.preventDefault(); // fire before textarea blur
                                    pickUser(user);
                                }}
                                className={`w-full flex items-center gap-2.5 px-3 py-2 text-left text-sm transition ${
                                    i === highlightIdx ? "bg-primary/5 text-primary" : "hover:bg-gray-50 text-gray-900"
                                }`}
                            >
                                <span className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0">
                                    {user.name.charAt(0)}
                                </span>
                                <span className="truncate font-medium">{user.name}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Render a comment body: turns @[Name](id) tokens into styled mention chips.
 * Exported for use by the comments list.
 */
export function renderBody(body) {
    const parts = [];
    let last = 0;
    const regex = /@\[([^\]]+)\]\((\d+)\)/g;
    let m;

    while ((m = regex.exec(body)) !== null) {
        if (m.index > last) parts.push(body.slice(last, m.index));
        parts.push(
            <span
                key={`${m.index}-${m[2]}`}
                className="inline-flex items-center font-semibold text-primary bg-primary/5 rounded px-1"
            >
                @{m[1]}
            </span>
        );
        last = m.index + m[0].length;
    }
    if (last < body.length) parts.push(body.slice(last));

    return parts;
}
