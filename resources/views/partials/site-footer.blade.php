<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h4><a href="{{ route('home') }}">Finance &amp; Future Technology</a></h4>
            <p>Exploring where money meets innovation with Mark Mpirirwe.</p>
        </div>
        <div>
            <h4>Topics</h4>
            <ul class="footer-links">
                @foreach($siteTopics->only(['finance', 'fintech', 'future-tech', 'policy-regulation']) as $topic)
                    <li><a href="{{ $topic['url'] }}">{{ $topic['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4>Media</h4>
            <ul class="footer-links">
                @if($siteTopics->has('interviews'))
                    <li><a href="{{ $siteTopics['interviews']['url'] }}">Interviews</a></li>
                @endif
                <li><a href="{{ route('search', ['q' => 'podcast']) }}">Podcasts</a></li>
                <li><a href="{{ route('search', ['q' => 'video']) }}">Videos</a></li>
                <li><a href="{{ route('events.index') }}">Events</a></li>
            </ul>
        </div>
        <div>
            <h4>Platform</h4>
            <ul class="footer-links">
                <li><a href="{{ route('about') }}">About</a></li>
                <li><a href="{{ route('home') }}#newsletter">Newsletter</a></li>
                <li><a href="{{ route('contact') }}">Contact</a></li>
                <li><a href="{{ route('privacy') }}">Privacy</a></li>
            </ul>
        </div>
    </div>
</footer>
