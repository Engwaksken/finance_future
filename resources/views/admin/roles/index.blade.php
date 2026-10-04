@extends('layouts.admin')
@section('title','Roles & Permissions')
@section('heading','Roles & Permissions')

@section('content')
<div class="panel">
<div class="panel-header"><h2 class="panel-title">Roles</h2><button class="btn btn-primary" type="button" data-modal-open="addRole"><i class="fa-solid fa-plus"></i> Add Role</button></div>
<div class="table-responsive"><table>
<thead><tr><th>Role</th><th>Users</th><th>Permissions</th><th>Actions</th></tr></thead>
<tbody>
@foreach($roles as $role)
<tr><td>{{ $role->name }}</td><td>{{ $role->users_count }}</td><td>{{ $role->slug==='super_admin' ? 'All permissions' : $role->permissions->pluck('name')->join(', ') }}</td><td><button class="btn-icon" type="button" data-modal-open="editRole{{ $role->id }}"><i class="fa-solid fa-pen"></i></button></td></tr>
<div class="modal-backdrop-custom" id="editRole{{ $role->id }}"><div class="modal-box modal-lg"><form method="POST" action="{{ route('admin.roles.update',$role) }}">@csrf @method('PUT')
<div class="modal-header"><h3 class="modal-title">Edit Role</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
<div class="modal-body">@include('admin.roles.partials.fields',['role'=>$role])</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>
@endforeach
</tbody></table></div>
</div>

<div class="modal-backdrop-custom" id="addRole"><div class="modal-box modal-lg"><form method="POST" action="{{ route('admin.roles.store') }}">@csrf
<div class="modal-header"><h3 class="modal-title">Add Role</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
<div class="modal-body">@include('admin.roles.partials.fields',['role'=>new \App\Models\Role()])</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Create Role</button></div>
</form></div></div>
@endsection
