<div class="field"><label>Name</label><input name="name" value="{{ old('name',$user->name) }}" required></div>
<div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
<div class="field"><label>Status</label><select name="status"><option value="active" @selected(old('status',$user->status ?? 'active')==='active')>Active</option><option value="inactive" @selected(old('status',$user->status)==='inactive')>Inactive</option></select></div>
<div class="field"><label>Roles</label><select name="roles[]" multiple size="5">@foreach($roles as $role)<option value="{{ $role->id }}" @selected(in_array($role->id,old('roles',$user->exists ? $user->roles->pluck('id')->all() : [])))>{{ $role->name }}</option>@endforeach</select></div>
<div class="field"><label>Password {{ $user->exists ? '(leave blank to keep current)' : '' }}</label><input type="password" name="password" {{ $user->exists ? '' : 'required' }}></div>
<div class="field"><label>Confirm Password</label><input type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }}></div>
