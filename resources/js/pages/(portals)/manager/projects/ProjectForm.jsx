import { useForm } from '@inertiajs/react';

const STATUS_OPTIONS = [
    { value: 'active',    label: 'Active',    color: '#22c55e' },
    { value: 'on_hold',   label: 'On Hold',   color: '#f59e0b' },
    { value: 'archived',  label: 'Archived',  color: '#94a3b8' },
];

export default function ProjectForm({ project = null, onSuccess }) {
    const isEdit = !!project;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name:        project?.name        ?? '',
        description: project?.description ?? '',
        status:      project?.status      ?? 'active',
        deadline:    project?.deadline    ?? '',
    });

    function submit(e) {
        e.preventDefault();
        if (isEdit) {
            put(`/manager/projects/${project.id}`, {

                onSuccess: onSuccess ?? undefined,
            });
        } else {
            post('/manager/projects', {

                onSuccess: onSuccess ?? undefined,
            });
        }
    }

    return (
        <form onSubmit={submit} style={styles.form}>

            {/* Name */}
            <div style={styles.field}>
                <label style={styles.label}>Project Name <span style={styles.req}>*</span></label>
                <input
                    style={{
                        ...styles.input,
                        ...(errors.name ? styles.inputError : {}),
                    }}
                    type="text"
                    value={data.name}
                    onChange={e => setData('name', e.target.value)}
                    placeholder="e.g. Q3 Marketing Campaign"
                    autoFocus
                />
                {errors.name && <p style={styles.errorMsg}>{errors.name}</p>}
            </div>

            {/* Description */}
            <div style={styles.field}>
                <label style={styles.label}>Description</label>
                <textarea
                    style={{
                        ...styles.input,
                        ...styles.textarea,
                        ...(errors.description ? styles.inputError : {}),
                    }}
                    value={data.description}
                    onChange={e => setData('description', e.target.value)}
                    placeholder="Briefly describe the project goals and scope…"
                    rows={4}
                />
                {errors.description && <p style={styles.errorMsg}>{errors.description}</p>}
            </div>

            {/* Status + Deadline row */}
            <div style={styles.row}>
                <div style={{ ...styles.field, flex: 1 }}>
                    <label style={styles.label}>Status <span style={styles.req}>*</span></label>
                    <div style={styles.statusGrid}>
                        {STATUS_OPTIONS.map(opt => (
                            <button
                                key={opt.value}
                                type="button"
                                onClick={() => setData('status', opt.value)}
                                style={{
                                    ...styles.statusBtn,
                                    borderColor: data.status === opt.value ? opt.color : 'transparent',
                                    background: data.status === opt.value
                                        ? opt.color + '18'
                                        : 'var(--color-surface)',
                                    color: data.status === opt.value ? opt.color : 'var(--color-text-muted)',
                                }}
                            >
                                <span style={{
                                    ...styles.dot,
                                    background: opt.color,
                                    opacity: data.status === opt.value ? 1 : 0.35,
                                }} />
                                {opt.label}
                            </button>
                        ))}
                    </div>
                    {errors.status && <p style={styles.errorMsg}>{errors.status}</p>}
                </div>

                <div style={{ ...styles.field, flex: 1 }}>
                    <label style={styles.label}>Deadline</label>
                    <input
                        style={{
                            ...styles.input,
                            ...(errors.deadline ? styles.inputError : {}),
                        }}
                        type="date"
                        value={data.deadline}
                        onChange={e => setData('deadline', e.target.value)}
                    />
                    <p style={styles.hint}>Required to enable the burndown chart.</p>
                    {errors.deadline && <p style={styles.errorMsg}>{errors.deadline}</p>}
                </div>
            </div>

            {/* Actions */}
            <div style={styles.actions}>
                <a href="/manager/projects" style={styles.cancelBtn}>
                    Cancel
                </a>
                <button type="submit" disabled={processing} style={styles.submitBtn}>
                    {processing
                        ? (isEdit ? 'Saving…' : 'Creating…')
                        : (isEdit ? 'Save Changes' : 'Create Project')
                    }
                </button>
            </div>
        </form>
    );
}

const styles = {
    form: {
        display: 'flex',
        flexDirection: 'column',
        gap: '1.5rem',
    },
    field: {
        display: 'flex',
        flexDirection: 'column',
        gap: '0.375rem',
    },
    row: {
        display: 'flex',
        gap: '1.25rem',
        flexWrap: 'wrap',
    },
    label: {
        fontSize: '0.8125rem',
        fontWeight: 600,
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: 'var(--color-text-muted)',
    },
    req: {
        color: '#f87171',
        marginLeft: '2px',
    },
    input: {
        width: '100%',
        padding: '0.625rem 0.875rem',
        borderRadius: '8px',
        border: '1.5px solid var(--color-border)',
        background: 'var(--color-surface)',
        color: 'var(--color-text)',
        fontSize: '0.9375rem',
        outline: 'none',
        transition: 'border-color 0.15s',
        boxSizing: 'border-box',
    },
    textarea: {
        resize: 'vertical',
        minHeight: '100px',
        fontFamily: 'inherit',
    },
    inputError: {
        borderColor: '#f87171',
    },
    errorMsg: {
        fontSize: '0.8rem',
        color: '#f87171',
        margin: 0,
    },
    hint: {
        fontSize: '0.78rem',
        color: 'var(--color-text-muted)',
        margin: '0.25rem 0 0',
        opacity: 0.7,
    },
    statusGrid: {
        display: 'flex',
        gap: '0.625rem',
        flexWrap: 'wrap',
    },
    statusBtn: {
        display: 'flex',
        alignItems: 'center',
        gap: '0.5rem',
        padding: '0.5rem 1rem',
        borderRadius: '8px',
        border: '1.5px solid transparent',
        cursor: 'pointer',
        fontSize: '0.875rem',
        fontWeight: 500,
        transition: 'all 0.15s',
    },
    dot: {
        width: '8px',
        height: '8px',
        borderRadius: '50%',
        flexShrink: 0,
    },
    actions: {
        display: 'flex',
        justifyContent: 'flex-end',
        gap: '0.75rem',
        paddingTop: '0.5rem',
        borderTop: '1px solid var(--color-border)',
    },
    cancelBtn: {
        padding: '0.625rem 1.25rem',
        borderRadius: '8px',
        border: '1.5px solid var(--color-border)',
        background: 'transparent',
        color: 'var(--color-text-muted)',
        fontSize: '0.9rem',
        fontWeight: 500,
        textDecoration: 'none',
        cursor: 'pointer',
        display: 'inline-flex',
        alignItems: 'center',
    },
    submitBtn: {
        padding: '0.625rem 1.5rem',
        borderRadius: '8px',
        border: 'none',
        background: 'var(--color-primary)',
        color: '#fff',
        fontSize: '0.9rem',
        fontWeight: 600,
        cursor: 'pointer',
        transition: 'opacity 0.15s',
    },
};
