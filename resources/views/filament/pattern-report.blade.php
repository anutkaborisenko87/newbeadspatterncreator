<div class="space-y-3">
    <strong>{{ $report->title }}</strong>
    @if ($report->drawing || $report->preview)
        <img src="{{ $report->drawing ? route('pattern-reports.preview', $report) : $report->preview }}" alt="{{ $report->title }}">
    @endif
    <p>{{ __('pattern_reports.topics.'.$report->topic) }}</p>
    <p class="whitespace-pre-wrap">{{ $report->message }}</p>
</div>
