@if(($count ?? 0) > 0)
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-900"
          title="{{ (int) $count }} later run(s) were queued as retries from this failure">
        <i data-lucide="refresh-cw" class="w-3 h-3 mr-0.5" aria-hidden="true"></i>
        Retried{{ (int) $count > 1 ? ' ('.(int) $count.')' : '' }}
    </span>
@endif
