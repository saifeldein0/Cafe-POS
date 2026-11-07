@php
    use App\Support\Duration;
    use App\Support\Money;

    /** @var \App\Services\ShiftSummary $summary */
    $row = 'flex items-baseline justify-between gap-4 py-2.5';
@endphp

{{-- The same receipt as the till's, in the panel's own style: recomputed from the orders on every visit. --}}
<div class="max-w-xl">
    <x-filament::section :heading="__('Shift receipt')">
        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm tabular-nums">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Started') }}</dt>
            <dd class="text-end font-medium text-gray-950 dark:text-white">{{ $summary->startedAt->format('d/m/Y H:i') }}</dd>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Ended') }}</dt>
            <dd class="text-end font-medium text-gray-950 dark:text-white">
                @if ($summary->endedAt)
                    {{ $summary->endedAt->format('d/m/Y H:i') }}
                @else
                    <x-filament::badge color="success" class="inline-flex">{{ __('In progress') }}</x-filament::badge>
                @endif
            </dd>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Duration') }}</dt>
            <dd class="text-end font-medium text-gray-950 dark:text-white">{{ Duration::between($summary->startedAt, $summary->endedAt) }}</dd>
        </dl>

        <div class="mt-4 border-t border-dashed border-gray-300 pt-2 dark:border-white/15">
            @forelse ($summary->lines as $line)
                <div class="{{ $row }} border-b border-gray-100 text-sm last:border-b-0 dark:border-white/5">
                    <span class="min-w-0 flex-1 text-gray-950 dark:text-white">{{ $line['name'] }}</span>
                    <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">× {{ $line['quantity'] }}</span>
                    <span class="w-28 shrink-0 text-end whitespace-nowrap tabular-nums text-gray-700 dark:text-gray-300">{{ Money::format($line['amount']) }}</span>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No sales in this shift.') }}</p>
            @endforelse
        </div>

        <div class="mt-2 border-t border-dashed border-gray-300 pt-2 text-sm dark:border-white/15">
            <div class="{{ $row }}">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Orders') }}</span>
                <span class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $summary->ordersCount }}</span>
            </div>
            @if ($summary->cancelledCount > 0)
                <div class="{{ $row }} text-danger-600 dark:text-danger-400">
                    <span>{{ __('Cancelled orders (not counted): :count', ['count' => $summary->cancelledCount]) }}</span>
                    <span class="font-medium whitespace-nowrap tabular-nums">{{ Money::format($summary->cancelledTotal) }}</span>
                </div>
            @endif
            <div class="mt-1 flex items-baseline justify-between gap-4 border-t-2 border-gray-950 pt-3 dark:border-white">
                <span class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Total') }}</span>
                <span class="text-2xl font-bold tracking-tight whitespace-nowrap tabular-nums text-gray-950 dark:text-white">{{ Money::format($summary->total) }}</span>
            </div>
        </div>
    </x-filament::section>
</div>
