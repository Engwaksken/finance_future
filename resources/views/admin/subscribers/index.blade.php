@extends('layouts.admin')
@section('title', 'Subscribers')
@section('heading', 'Subscribers')

@section('content')
<x-admin-stats :stats="$stats" :period="$period" />
<div class="panel">
    <div class="panel-header"><div><h2 class="panel-title">Newsletter Subscribers</h2><p class="text-muted">Filter by subscription date. Older records without a subscription date use their creation date.</p></div></div>
    <div class="panel-body">
        <form method="GET" class="admin-filters">
            <div class="filter-field"><label for="subscriber-search">Search</label><input id="subscriber-search" name="q" value="{{ request('q') }}" placeholder="Name or email" maxlength="200"></div>
            <div class="filter-field"><label for="subscriber-status">Status</label><select id="subscriber-status" name="status"><option value="">All statuses</option>@foreach(['active', 'inactive', 'unsubscribed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <x-admin-period-filter :period="$period" />
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button><a class="btn btn-light" href="{{ route('admin.subscribers.index') }}">Reset</a>
        </form>
    </div>
    <div class="table-responsive"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Subscribed</th><th>Unsubscribed</th></tr></thead>
        <tbody>@forelse($subscribers as $subscriber)
            <tr><td>{{ $subscriber->name ?: '—' }}</td><td>{{ $subscriber->email }}</td><td><span class="badge {{ $subscriber->status === 'active' ? 'badge-success' : 'badge-grey' }}">{{ ucfirst($subscriber->status) }}</span></td><td>{{ ($subscriber->subscribed_at ?? $subscriber->created_at)?->format('d M Y, H:i') }}</td><td>{{ $subscriber->unsubscribed_at?->format('d M Y, H:i') ?? '—' }}</td></tr>
        @empty<tr><td colspan="5" class="empty-state">No subscribers match these filters.</td></tr>@endforelse</tbody>
    </table></div>
    @if($subscribers->hasPages())<div class="panel-body">{{ $subscribers->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
