@props(['summary'])

@php
    use App\Support\Money;

    /** @var \App\Services\ShiftSummary $summary */
@endphp

{{-- A shift's receipt, printed-ticket style. The product lines scroll on their own; the total always shows. --}}
<article {{ $attributes->class('pos-ticket flex min-h-0 flex-col bg-white text-ink shadow-[0_18px_40px_-24px_rgb(34_26_21/0.55)]') }}
         aria-label="{{ __('Shift receipt') }}"
         data-test="shift-receipt">
    <header class="flex flex-col items-center gap-1 border-b-2 border-dashed border-line-strong px-6 pt-5 pb-4 text-center">
        <x-brand-mark class="size-8 text-brand" />
        <h2 class="font-display text-2xl leading-tight font-semibold">{{ __('Shift receipt') }}</h2>
        <dl class="mt-2 grid w-full grid-cols-[auto_1fr] gap-x-4 gap-y-0.5 text-start text-lg tabular-nums">
            <dt class="text-muted">{{ __('Started') }}</dt>
            <dd class="text-end font-semibold">{{ $summary->startedAt->format('d/m/Y H:i') }}</dd>
            <dt class="text-muted">{{ __('Ended') }}</dt>
            <dd class="text-end font-semibold">{{ $summary->endedAt?->format('d/m/Y H:i') ?? __('In progress') }}</dd>
        </dl>
    </header>

    <div class="pos-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-2">
        @forelse ($summary->lines as $line)
            <div class="flex items-baseline gap-3 border-b border-line py-2 text-lg last:border-b-0">
                <span class="min-w-0 flex-1 truncate font-semibold">{{ $line['name'] }}</span>
                <span class="shrink-0 tabular-nums text-muted">× {{ $line['quantity'] }}</span>
                <span class="w-32 shrink-0 text-end font-semibold tabular-nums">{{ Money::format($line['amount']) }}</span>
            </div>
        @empty
            <p class="py-6 text-center text-lg text-muted">{{ __('No sales in this shift.') }}</p>
        @endforelse
    </div>

    <footer class="border-t-2 border-dashed border-line-strong px-6 pt-3 pb-6">
        <div class="flex items-baseline justify-between gap-3 text-lg">
            <span class="text-muted">{{ __('Orders') }}</span>
            <span class="font-semibold tabular-nums" data-test="receipt-orders">{{ $summary->ordersCount }}</span>
        </div>
        @if ($summary->cancelledCount > 0)
            <div class="mt-1 flex items-baseline justify-between gap-3 text-lg text-stop" data-test="receipt-cancelled">
                <span>{{ __('Cancelled orders (not counted): :count', ['count' => $summary->cancelledCount]) }}</span>
                <span class="font-semibold tabular-nums">{{ Money::format($summary->cancelledTotal) }}</span>
            </div>
        @endif
        <div class="mt-3 flex items-baseline justify-between gap-3 border-t-2 border-ink pt-3">
            <span class="font-display text-2xl font-bold uppercase tracking-wide">{{ __('Total') }}</span>
            <span class="font-display text-[2.5rem] leading-none font-bold tracking-tight tabular-nums" data-test="receipt-total">{{ Money::format($summary->total) }}</span>
        </div>
    </footer>
</article>
