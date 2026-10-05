@extends('layouts.frontend')
@section('title', 'Contact | Finance & Future Technology')
@section('content')
<section class="section">
    <div class="container page-copy">
        <div class="eyebrow">Get in touch</div>
        <h1>Contact</h1>
        <p>For questions about the platform, interviews, events, or collaborations, contact us by email.</p>
        @php($email = \App\Models\SiteSetting::valueOf('site_email'))
        @if($email && filter_var($email, FILTER_VALIDATE_EMAIL))
            <a class="btn btn-primary" href="mailto:{{ $email }}">{{ $email }}</a>
        @else
            <p>Our contact email will be published here soon.</p>
        @endif
    </div>
</section>
@endsection
