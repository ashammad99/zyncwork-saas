import React from "react";
import { useForm, router } from "@inertiajs/react";
import { X, Calendar, Clock } from "lucide-react";

/**
 * @param {object} props
 * @param {number} props.taskId
 * @param {() => void} props.onClose
 */
export default function ManualEntryModal({ taskId, onClose }) {
    const today = new Date().toISOString().slice(0, 10);

    const { data, setData, reset } = useForm({
        date: today,
        start_time: "09:00",
        end_time: "10:00",
        notes: "",
    });

    const [processing, setProcessing] = React.useState(false);
    const [errors, setErrors] = React.useState({});

    const submit = () => {
        setErrors({});
        setProcessing(true);

        router.post(
            `/tasks/${taskId}/time-logs/manual`,
            {
                date:       data.date,
                started_at: data.start_time,   // "09:00" — HH:MM only
                ended_at:   data.end_time,     // "10:00" — HH:MM only
                notes:      data.notes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onClose();
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            }
        );
    };

    const durationMin = computeDuration(data.start_time, data.end_time);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            onClick={onClose}
        >
            <div
                className="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">Log Time Manually</h2>
                        <p className="text-xs text-gray-500 mt-0.5">
                            For work you did without the timer running
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition"
                    >
                        <X size={20} />
                    </button>
                </div>

                {/* Body */}
                <div className="p-6 space-y-4">
                    {/* Date */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1">
                            <Calendar size={12} />
                            Date
                        </label>
                        <input
                            type="date"
                            value={data.date}
                            onChange={(e) => setData("date", e.target.value)}
                            max={today}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        />
                    </div>

                    {/* Time range */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1">
                            <Clock size={12} />
                            Time
                        </label>
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <input
                                    type="time"
                                    value={data.start_time}
                                    onChange={(e) => setData("start_time", e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                                />
                                <p className="text-[10px] text-gray-400 mt-0.5">Start</p>
                            </div>
                            <div>
                                <input
                                    type="time"
                                    value={data.end_time}
                                    onChange={(e) => setData("end_time", e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                                />
                                <p className="text-[10px] text-gray-400 mt-0.5">End</p>
                            </div>
                        </div>
                        {errors.started_at && (
                            <p className="mt-1 text-xs text-red-600">{errors.started_at}</p>
                        )}
                        {errors.ended_at && (
                            <p className="mt-1 text-xs text-red-600">{errors.ended_at}</p>
                        )}
                    </div>

                    {/* Duration preview */}
                    {durationMin > 0 && (
                        <div className="text-center py-2 bg-primary/5 rounded-lg text-sm text-primary font-semibold">
                            {formatDuration(durationMin)}
                        </div>
                    )}

                    {/* Notes */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                            Notes <span className="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <textarea
                            value={data.notes}
                            onChange={(e) => setData("notes", e.target.value)}
                            rows={3}
                            placeholder="What did you work on?"
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition resize-none"
                        />
                        {errors.notes && (
                            <p className="mt-1 text-xs text-red-600">{errors.notes}</p>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={submit}
                            disabled={processing || durationMin <= 0}
                            className="px-4 py-2 text-sm bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                        >
                            {processing ? "Saving…" : "Save Entry"}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function computeDuration(start, end) {
    if (!start || !end) return 0;
    const [sh, sm] = start.split(":").map(Number);
    const [eh, em] = end.split(":").map(Number);
    return eh * 60 + em - (sh * 60 + sm);
}

function formatDuration(min) {
    if (min <= 0) return "";
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h === 0) return `${m} minutes`;
    if (m === 0) return `${h} hour${h !== 1 ? "s" : ""}`;
    return `${h}h ${m}m`;
}
