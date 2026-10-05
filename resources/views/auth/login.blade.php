@extends('layouts.auth')

@section('title', __('Log in'))
@section('eyebrow', __('Your account'))
@section('heading', __('Welcome back'))
@section('description', __('Enter your details to sign in to your account.'))

@section('content')
<form class="auth-form" method="POST" action="{{ route('login') }}">
    @csrf

    <x-auth-field name="email" type="email" :label="__('Email address')"
                  placeholder="you@example.com" autocomplete="username" autofocus />

    <x-auth-field name="password" type="password" :label="__('Password')"
                  :placeholder="__('Enter your password')" autocomplete="current-password" />

    <div class="auth-form-options">
        <label class="auth-checkbox" for="remember_me">
            <input id="remember_me" type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>{{ __('Remember me') }}</span>
        </label>
        @if(Route::has('password.request'))
            <a class="auth-link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
        @endif
    </div>

    <button class="auth-submit" type="submit">{{ __('Log in') }} <span aria-hidden="true">&rarr;</span></button>
</form>

@if(Route::has('register'))
    <p class="auth-secondary">{{ __('New here?') }} <a class="auth-link" href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
@endif
@endsection
