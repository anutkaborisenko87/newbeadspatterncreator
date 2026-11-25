<div class="space-y-3">
    <p><strong>{{ __('reports.target') }}</strong> {{ $report->reported_login }}</p>
    <p><strong>{{ $report->pattern_title }}</strong></p>
    <blockquote>{{ $report->comment_text }}</blockquote>
    <p class="whitespace-pre-wrap">{{ $report->message }}</p>
    @foreach ($report->evidence as $index => $path)
        <a href="{{ route('reports.evidence', [$report, $index]) }}" target="_blank" rel="noopener">
            <img src="{{ route('reports.evidence', [$report, $index]) }}" width="240" alt="{{ __('reports.evidence') }} {{ $index + 1 }}">
        </a>
    @endforeach
    @if ($report->decision)
        <p><strong>{{ __('reports.decision') }}</strong> {{ $report->decision }}</p>
    @endif
</div>
