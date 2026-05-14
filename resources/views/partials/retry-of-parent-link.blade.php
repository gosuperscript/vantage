@if($job->retried_from_id)
    <a href="{{ route('vantage.jobs.show', $job->retried_from_id) }}"
       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-900 hover:bg-amber-200 shrink-0"
       title="Manual retry of Vantage run #{{ $job->retried_from_id }}">
        <i data-lucide="corner-down-right" class="w-3 h-3 mr-0.5" aria-hidden="true"></i>
        Retry of #{{ $job->retried_from_id }}
    </a>
@endif
