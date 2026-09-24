
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }

    .header { border-bottom: 3px solid #6366f1; padding-bottom: 12px; margin-bottom: 16px; }
.header h1 { font-size: 20px; margin: 0 0 2px; color: #111827; }
    .header .sub { font-size: 10px; color: #6b7280; }

    .meta { margin-bottom: 14px; font-size: 10px; color: #4b5563; }
    .meta strong { color: #111827; }

    table { width: 100%; border-collapse: collapse; }
        th {
    background: #f3f4f6; text-align: left; padding: 6px 8px;
    font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #6b7280; border-bottom: 2px solid #e5e7eb;
        }
        td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }

    .num { text-align: right; }
        .badge { font-size: 8px; padding: 1px 5px; border-radius: 3px; }
        .badge-approved { background: #d1fae5; color: #065f46; }
    .badge-pending  { background: #fef3c7; color: #92400e; }

    .totals { margin-top: 14px; text-align: right; font-size: 11px; }
        .totals strong { font-size: 13px; }

        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center;
    font-size: 8px; color: #9ca3af; padding: 6px 0; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Zyncwork — Time Log Report</h1>
        <div class="sub">Generated {{ now()->format('M j, Y H:i') }} by {{ $generatedBy }}</div>
</div>

<div class="meta">
    <strong>Period:</strong> {{ $dateFrom ?? '—' }} → {{ $dateTo ?? '—' }}
    @if($departmentName) &nbsp;|&nbsp; <strong>Department:</strong> {{ $departmentName }} @endif
    &nbsp;|&nbsp; <strong>Entries:</strong> {{ $logs->count() }}
</div>

<table>
    <thead>
    <tr>
        <th>Employee</th>
        <th>Project / Task</th>
        <th>Date</th>
        <th>Start</th>
        <th>End</th>
        <th class="num">Hours</th>
        <th>Type</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($logs as $log)
        <tr>
            <td>{{ $log->user?->name }}</td>
            <td>
                {{ $log->task?->project?->name }}<br>
                <span style="color:#6b7280">{{ \Illuminate\Support\Str::limit($log->task?->title, 40) }}</span>
            </td>
            <td>{{ $log->started_at?->format('Y-m-d') }}</td>
            <td>{{ $log->started_at?->format('H:i') }}</td>
            <td>{{ $log->ended_at?->format('H:i') }}</td>
            <td class="num">{{ $log->duration_minutes !== null ? number_format($log->duration_minutes / 60, 2) : '—' }}</td>
            <td>{{ $log->entry_type }}</td>
            <td>
                @if($log->approved_by)
                    <span class="badge badge-approved">Approved</span>
                @else
                    <span class="badge badge-pending">Pending</span>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="totals">
    Total: <strong>{{ number_format($logs->sum('duration_minutes') / 60, 2) }} hours</strong>
    ({{ $logs->count() }} entries, {{ $logs->whereNotNull('approved_by')->count() }} approved)
</div>

<div class="footer">Zyncwork · Confidential · Page <span class="pagenum"></span></div>

</body>
</html>
