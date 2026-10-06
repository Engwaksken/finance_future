@props(['stats', 'period' => null])
@if($period)<p class="text-muted">Statistics for {{ $period->label() }}{{ request()->filled('q') || request()->filled('status') || request()->filled('action') || request()->filled('actor_id') || request()->filled('subject_type') ? ', matching the selected filters' : '' }}.</p>@endif
<div class="stats-grid">
    @foreach($stats as $label => $value)
        <div class="stat-card {{ ['border-blue', 'border-green', 'border-orange', 'border-purple'][$loop->index % 4] }}">
            <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ number_format($value) }}</div></div>
            <div class="stat-icon"><i class="fa-solid {{ ['fa-chart-simple', 'fa-circle-check', 'fa-clock', 'fa-chart-line'][$loop->index % 4] }}" aria-hidden="true"></i></div>
        </div>
    @endforeach
</div>
