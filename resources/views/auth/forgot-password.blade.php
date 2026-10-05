@extends('layouts.auth')

@section('title', __('Forgot password'))
@section('eyebrow', __('Account recovery'))
@section('heading', __('Forgot your password?'))
@section('description', __('Enter the email address associated with your account and we’ll send you a link to reset your password.'))

@section('content')
<form class="auth-form" method="POST" action="{{ route('password.email') }}">
    @csrf

    <x-auth-field name="email" type="email" :label="__('Email address')"
                  placeholder="you@example.com" autocomplete="email" autofocus />

    <button class="auth-submit" type="submit">{{ __('Send reset link') }} <span aria-hidden="true">&rarr;</span></button>
</form>

<p class="auth-help">{{ __('Check your inbox and spam folder for the password reset email.') }}</p>
<p class="auth-secondary"><a class="auth-link" href="{{ route('login') }}"><span aria-hidden="true">&larr;</span> {{ __('Back to log in') }}</a></p>
@endsection
