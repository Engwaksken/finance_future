@extends('layouts.auth')
@section('title', __('Confirm password'))
@section('eyebrow', __('Your account'))
@section('heading', __('Confirm your password'))
@section('description', __('Please enter your password to continue to this secure area.'))

@section('content')
<form class="auth-form" method="POST" action="{{ route('password.confirm') }}">
    @csrf
    <x-auth-field name="password" type="password" :label="__('Password')" autocomplete="current-password" autofocus />
    <button class="auth-submit" type="submit">{{ __('Confirm') }}</button>
</form>
@endsection
