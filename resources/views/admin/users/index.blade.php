@extends('layouts.admin')
@section('title','Users')
@section('heading','Users')

@section('content')
<x-admin-stats :stats="$stats" :period="$period" />
<div class="panel">
<div class="panel-header"><h2 class="panel-title">System Users</h2><button class="btn btn-primary" data-modal-open="addUser" type="button"><i class="fa-solid fa-user-plus"></i> Add User</button></div>
<div class="panel-body">
    <form method="GET" class="admin-filters">
        <div class="filter-field"><label for="user-search">Search</label><input id="user-search" name="q" value="{{ request('q') }}" placeholder="Name or email" maxlength="200"></div>
        <div class="filter-field"><label for="user-status">Status</label><select id="user-status" name="status"><option value="">All statuses</option>@foreach(['active', 'inactive'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <x-admin-period-filter :period="$period" />
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button><a class="btn btn-light" href="{{ route('admin.users.index') }}">Reset</a>
    </form>
</div>
<div class="table-responsive">
<table>
<thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
@forelse($users as $user)
<tr>
<td>{{ $user->name }}</td><td>{{ $user->email }}</td>
<td>{{ $user->roles->pluck('name')->join(', ') ?: ucfirst($user->role ?? 'user') }}</td>
<td><span class="badge {{ $user->status === 'active' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($user->status ?? 'active') }}</span></td>
<td><div class="table-actions"><button class="btn-icon" data-modal-open="editUser{{ $user->id }}" type="button"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-icon-danger" data-modal-open="deleteUser{{ $user->id }}" type="button"><i class="fa-solid fa-trash"></i></button></div></td>
</tr>

<div class="modal-backdrop-custom" id="editUser{{ $user->id }}"><div class="modal-box"><form method="POST" action="{{ route('admin.users.update',$user) }}">@csrf @method('PUT')
<div class="modal-header"><h3 class="modal-title">Edit User</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
<div class="modal-body">@include('admin.users.partials.fields',['user'=>$user])</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>

<div class="modal-backdrop-custom" id="deleteUser{{ $user->id }}"><div class="modal-box modal-sm"><form method="POST" action="{{ route('admin.users.destroy',$user) }}">@csrf @method('DELETE')
<div class="modal-header"><h3 class="modal-title">Delete User</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
<div class="modal-body">Delete <strong>{{ $user->name }}</strong>?</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-danger">Delete</button></div>
</form></div></div>
@empty
<tr><td colspan="5" class="empty-state">No users match these filters.</td></tr>
@endforelse
</tbody>
</table>
</div>
@if($users->hasPages())<div class="panel-body">{{ $users->links('pagination::bootstrap-4') }}</div>@endif
</div>

<div class="modal-backdrop-custom" id="addUser"><div class="modal-box"><form method="POST" action="{{ route('admin.users.store') }}">@csrf
<div class="modal-header"><h3 class="modal-title">Add User</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
<div class="modal-body">@include('admin.users.partials.fields',['user'=>new \App\Models\User()])</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Create User</button></div>
</form></div></div>
@endsection
