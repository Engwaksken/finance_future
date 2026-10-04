<div class="field"><label>Role Name</label><input name="name" value="{{ old('name',$role->name) }}" required></div>
<div class="field"><label>Description</label><textarea name="description" rows="3">{{ old('description',$role->description) }}</textarea></div>
@if($role->slug !== 'super_admin')
<div class="permission-grid">
@foreach($permissions as $group => $items)
<div class="permission-group"><strong>{{ ucfirst($group) }}</strong>
@foreach($items as $permission)
<label class="checkbox-label"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id,old('permissions',$role->exists ? $role->permissions->pluck('id')->all() : [])))> {{ $permission->name }}</label>
@endforeach
</div>
@endforeach
</div>
@else
<div class="alert alert-success">Super Admin automatically has every permission.</div>
@endif
