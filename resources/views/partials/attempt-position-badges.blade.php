@php
    $uuid = $job->uuid ?? null;
    $attempt = (int) ($job->attempt ?? 0);
    $bounds = ($uuid && isset($attemptBounds[$uuid])) ? $attemptBounds[$uuid] : null;
    $minAttempt = $bounds ? (int) $bounds['min_attempt'] : null;
    $maxAttempt = $bounds ? (int) $bounds['max_attempt'] : null;
    $hasMultipleAttempts = $minAttempt !== null && $maxAttempt !== null && $minAttempt < $maxAttempt;
@endphp
@if(! $uuid)
    <span class="text-gray-400">—</span>
@else
    <div class="inline-flex flex-nowrap items-center gap-1.5 whitespace-nowrap">
        <span class="text-gray-700 text-xs font-medium tabular-nums shrink-0"
              title="Queue worker attempt number for this job UUID">
            #{{ $attempt }}
        </span>
        @if($hasMultipleAttempts)
            @if($attempt === $minAttempt)
                <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800"
                      title="First recorded attempt for this job UUID">
                    First
                </span>
            @endif
            @if($attempt === $maxAttempt)
                <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-900"
                      title="Latest recorded attempt for this job UUID">
                    Last
                </span>
            @endif
        @endif
    </div>
@endif
