@extends('layouts.frontend')
@section('title', 'Privacy | Finance & Future Technology')
@section('content')
<section class="section">
    <div class="container page-copy">
        <div class="eyebrow">Your information</div>
        <h1>Privacy</h1>
        <h2>Accounts</h2>
        <p>Creating an account stores your name, email address, and a hashed password. Your email address is also used when you request a password reset.</p>
        <h2>Newsletter</h2>
        <p>Subscribing stores the name and email address you provide, along with your subscription status.</p>
        <h2>Cookies</h2>
        <p>The site uses session cookies to keep you signed in and protect forms. Choosing “Remember me” also uses a cookie to remember your sign-in.</p>
        <h2>Questions</h2>
        <p>Visit our <a href="{{ route('contact') }}">contact page</a> with questions about your information.</p>
    </div>
</section>
@endsection
