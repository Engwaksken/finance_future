@extends('layouts.auth')
@section('title', __('Reset password'))
@section('eyebrow', __('Account recovery'))
@section('heading', __('Choose a new password'))
@section('description', __('Enter your new password below to get back into your account.'))

@section('content')
<form class="auth-form" method="POST" action="{{ route('password.store') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <x-auth-field name="email" type="email" :label="__('Email address')"
                  :value="$request->email" autocomplete="username" autofocus />
    <x-auth-field name="password" type="password" :label="__('New password')" autocomplete="new-password" />
    <x-auth-field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />

    <button class="auth-submit" type="submit">{{ __('Reset password') }}</button>
</form>
@endsection
