<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Profile Request Report' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        .header { text-align: center; margin-bottom: 10px; }
        .logo { color: #006A4E; font-size: 16px; font-weight: bold; }
        .meta { font-size: 8px; color: #666; margin-top: 3px; }
        h3 { margin-top: 12px; font-size: 11pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>{{ $subtitle ?? 'Profile Request Report' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? '' }} | By: {{ $generated_by ?? '-' }} | Period: {{ $from_date ?? 'All Time' }} to {{ $to_date ?? 'Present' }}</div>
    </div>

    @if(!empty($summary))
    <h3>Summary</h3>
    <table>
        <tr><td><strong>Total Requests</strong></td><td>{{ $summary['total'] ?? 0 }}</td></tr>
        <tr><td><strong>Pending</strong></td><td>{{ $summary['pending'] ?? 0 }}</td></tr>
        <tr><td><strong>Processed</strong></td><td>{{ $summary['processed'] ?? 0 }}</td></tr>
        <tr><td><strong>Approved</strong></td><td>{{ $summary['approved'] ?? 0 }}</td></tr>
        <tr><td><strong>Rejected</strong></td><td>{{ $summary['rejected'] ?? 0 }}</td></tr>
        <tr><td><strong>Approval Rate</strong></td><td>{{ $summary['approval_rate'] ?? 0 }}%</td></tr>
        <tr><td><strong>Avg Processing (days)</strong></td><td>{{ $summary['avg_processing_days'] ?? '-' }}</td></tr>
    </table>
    @endif

    @if(!empty($by_type))
    <h3>By Request Type</h3>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Total</th>
                <th>Pending</th>
                <th>Approved</th>
                <th>Rejected</th>
            </tr>
        </thead>
        <tbody>
            @foreach($by_type as $type => $stats)
            <tr>
                <td>{{ $type ?: 'N/A' }}</td>
                <td>{{ $stats['total'] ?? 0 }}</td>
                <td>{{ $stats['pending'] ?? 0 }}</td>
                <td>{{ $stats['approved'] ?? 0 }}</td>
                <td>{{ $stats['rejected'] ?? 0 }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <h3>Request List</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Employee</th>
                <th>Type</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Reviewed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests ?? [] as $r)
            <tr>
                <td>{{ is_array($r) ? ($r['id'] ?? '-') : ($r->id ?? '-') }}</td>
                <td>
                    @if(is_array($r) && !empty($r['employee']))
                        {{ trim(($r['employee']['first_name'] ?? '') . ' ' . ($r['employee']['last_name'] ?? '')) ?: '-' }}
                    @elseif(is_object($r) && isset($r->employee))
                        {{ $r->employee->first_name ?? '' }} {{ $r->employee->last_name ?? '' }}
                    @else
                        -
                    @endif
                </td>
                <td>{{ is_array($r) ? ($r['request_type'] ?? '-') : ($r->request_type ?? '-') }}</td>
                <td>
                    @if(is_array($r))
                        @if(($r['status'] ?? '') === 'pending') Pending
                        @elseif(!empty($r['is_approved'])) Approved
                        @else Rejected
                        @endif
                    @else
                        @if(($r->status ?? '') === 'pending') Pending
                        @elseif($r->is_approved ?? false) Approved
                        @else Rejected
                        @endif
                    @endif
                </td>
                <td>
                    @if(is_array($r) && !empty($r['created_at']))
                        {{ \Carbon\Carbon::parse($r['created_at'])->format('d M Y') }}
                    @elseif(is_object($r) && isset($r->created_at))
                        {{ $r->created_at->format('d M Y') }}
                    @else
                        -
                    @endif
                </td>
                <td>
                    @if(is_array($r) && !empty($r['reviewed_at']))
                        {{ \Carbon\Carbon::parse($r['reviewed_at'])->format('d M Y') }}
                    @elseif(is_object($r) && isset($r->reviewed_at) && $r->reviewed_at)
                        {{ $r->reviewed_at->format('d M Y') }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6">No profile requests found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
