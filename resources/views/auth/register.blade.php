@extends('layouts.auth')

@section('title', __('Create an account'))
@section('eyebrow', __('Join us'))
@section('heading', __('Create your account'))
@section('description', __('A few details and you’re ready to get started.'))

@section('content')
<form class="auth-form" method="POST" action="{{ route('register') }}">
    @csrf

    <x-auth-field name="name" :label="__('Full name')"
                  :placeholder="__('Your name')" autocomplete="name" autofocus />

    <x-auth-field name="email" type="email" :label="__('Email address')"
                  placeholder="you@example.com" autocomplete="email" />

    <x-auth-field name="password" type="password" :label="__('Password')"
                  :placeholder="__('Choose a password')" autocomplete="new-password" />

    <x-auth-field name="password_confirmation" type="password" :label="__('Confirm password')"
                  :placeholder="__('Enter your password again')" autocomplete="new-password" />

    <button class="auth-submit" type="submit">
        {{ __('Create account') }} <span aria-hidden="true">&rarr;</span>
    </button>
</form>

<p class="auth-secondary">
    {{ __('Already have an account?') }}
    <a class="auth-link" href="{{ route('login') }}">{{ __('Log in') }}</a>
</p>
@endsection
