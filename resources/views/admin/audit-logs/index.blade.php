@extends('layouts.admin')
@section('title', 'Audit Logs')
@section('heading', 'Audit Logs')

@section('content')
<x-admin-stats :stats="$stats" :period="$period" />
<div class="panel">
    <div class="panel-header"><div><h2 class="panel-title">Admin Audit Trail</h2><p class="text-muted">Changes to articles, users, categories, roles, settings, and slides are recorded from now on. Password and article-body values are redacted.</p></div></div>
    <div class="panel-body">
        <form method="GET" class="admin-filters">
            <div class="filter-field"><label for="audit-search">Search</label><input id="audit-search" name="q" value="{{ request('q') }}" maxlength="200" placeholder="Record, actor, or route"></div>
            <div class="filter-field"><label for="audit-action">Action</label><select id="audit-action" name="action"><option value="">All actions</option>@foreach(['created', 'updated', 'deleted'] as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst($action) }}</option>@endforeach</select></div>
            <div class="filter-field"><label for="audit-type">Record Type</label><select id="audit-type" name="subject_type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="filter-field"><label for="audit-actor">Actor</label><select id="audit-actor" name="actor_id"><option value="">All actors</option>@foreach($actors as $actor)<option value="{{ $actor->id }}" @selected(request('actor_id') == $actor->id)>{{ $actor->name }}</option>@endforeach</select></div>
            <x-admin-period-filter :period="$period" />
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button><a class="btn btn-light" href="{{ route('admin.audit-logs.index') }}">Reset</a>
        </form>
    </div>
    <div class="table-responsive"><table>
        <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Record</th><th>Details</th></tr></thead>
        <tbody>@forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('d M Y, H:i:s') }}</td><td>{{ $log->actor_name }}</td>
                <td><span class="badge {{ ['created' => 'badge-success', 'updated' => 'badge-info', 'deleted' => 'badge-danger'][$log->action] ?? 'badge-grey' }}">{{ ucfirst($log->action) }}</span></td>
                <td><strong>{{ $log->subject_label }}</strong><div class="text-muted">{{ $log->subject_type }} #{{ $log->subject_id }}</div></td>
                <td class="audit-details"><details><summary>View {{ count($log->changes) }} change(s)</summary>
                    <p class="text-muted">{{ $log->route }} · {{ $log->ip_address ?? 'Unknown IP' }}</p>
                    <div class="audit-changes">@foreach($log->changes as $field => $change)
                        <div class="audit-change"><strong>{{ ucwords(str_replace('_', ' ', $field)) }}</strong>
                            @if($change['redacted'] ?? false)<span>Changed — value not recorded.</span>@else
                                <span>Before: {{ isset($change['before']) ? json_encode($change['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</span>
                                <span>After: {{ isset($change['after']) ? json_encode($change['after'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</span>
                            @endif
                        </div>
                    @endforeach</div>
                </details></td>
            </tr>
        @empty<tr><td colspan="5" class="empty-state">No audit events match these filters.</td></tr>@endforelse</tbody>
    </table></div>
    @if($logs->hasPages())<div class="panel-body">{{ $logs->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
