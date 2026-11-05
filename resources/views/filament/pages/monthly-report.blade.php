@php
    use App\Support\Money;

    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ' '), '0'), '.') ?: '0';

    // One table style for the whole report: quiet grey headers, ink names, right-aligned figures that
    // never wrap (on a phone the table scrolls inside its own frame instead).
    $table = 'w-full text-sm';
    $th = 'px-3 py-2.5 text-start text-[0.8125rem] font-medium text-gray-500 dark:text-gray-400';
    $thNum = 'px-3 py-2.5 text-end text-[0.8125rem] font-medium text-gray-500 dark:text-gray-400';
    $td = 'px-3 py-2.5 text-gray-950 dark:text-white';
    $tdNum = 'px-3 py-2.5 text-end whitespace-nowrap tabular-nums text-gray-700 dark:text-gray-300';
    $tf = 'px-3 pt-3 pb-1 font-semibold text-gray-950 dark:text-white';
    $tfNum = 'px-3 pt-3 pb-1 text-end font-semibold whitespace-nowrap tabular-nums text-gray-950 dark:text-white';

    // The "estimated" flag: small and amber, so it is noticed without shouting over the figure.
    $flag = 'me-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-md bg-warning-50 px-1 text-xs font-bold text-warning-700 ring-1 ring-warning-600/20 ring-inset dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30';
@endphp

<x-filament-panels::page>
    <div class="max-w-xs">
        {{ $this->form }}
    </div>

    {{-- Totals: read top to bottom like a statement, ending on the net profit. --}}
    <x-filament::section :heading="__('Summary') . ' — ' . $monthLabel">
        <div class="grid gap-x-10 gap-y-5 lg:grid-cols-[minmax(0,34rem)_minmax(0,1fr)]">
            <dl class="tabular-nums">
                <div class="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2.5 dark:border-white/5">
                    <dt class="text-gray-600 dark:text-gray-400">{{ __('Sales') }}</dt>
                    <dd class="text-base font-medium whitespace-nowrap text-gray-950 dark:text-white">{{ Money::format($sales) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2.5 dark:border-white/5">
                    <dt class="text-gray-600 dark:text-gray-400">{{ __('Purchases this month') }}</dt>
                    <dd class="text-base font-medium whitespace-nowrap text-gray-950 dark:text-white">− {{ Money::format($purchases) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-4 py-2.5">
                    <dt class="text-gray-600 dark:text-gray-400">{{ __('Fixed expenses (monthly share)') }}</dt>
                    <dd class="text-base font-medium whitespace-nowrap text-gray-950 dark:text-white">− {{ Money::format($fixed) }}</dd>
                </div>
                <div class="mt-1 flex items-baseline justify-between gap-4 border-t-2 border-gray-950 pt-3 dark:border-white">
                    <dt class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Net profit') }}</dt>
                    <dd @class([
                        'text-2xl font-bold tracking-tight whitespace-nowrap',
                        'text-success-700 dark:text-success-400' => $netProfit >= 0,
                        'text-danger-600 dark:text-danger-400' => $netProfit < 0,
                    ])>{{ Money::format($netProfit) }}</dd>
                </div>
            </dl>
            <p class="self-end text-sm leading-relaxed text-gray-500 lg:border-s lg:border-gray-200 lg:ps-6 dark:text-gray-400 dark:lg:border-white/10">
                {{ __('Net profit = sales − purchases this month − monthly share of fixed expenses (cash basis). Cancelled orders are excluded.') }}
            </p>
        </div>
    </x-filament::section>

    {{-- Per-product sales --}}
    <x-filament::section :heading="__('Sales by product')">
        @if ($products->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No sales this month.') }}</p>
        @else
            <div class="-mx-3 overflow-x-auto">
                <table class="{{ $table }}">
                    <thead class="border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th class="{{ $th }}">{{ __('Product') }}</th>
                            <th class="{{ $thNum }}">{{ __('Qty') }}</th>
                            <th class="{{ $thNum }}">{{ __('Revenue') }}</th>
                            <th class="{{ $thNum }}">{{ __('Estimated cost') }}</th>
                            <th class="{{ $thNum }}">{{ __('Estimated margin') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($products as $row)
                            <tr>
                                <td class="{{ $td }}">{{ $row->product_name }}</td>
                                <td class="{{ $tdNum }}">{{ $row->quantity }}</td>
                                <td class="{{ $tdNum }}">{{ Money::format($row->revenue) }}</td>
                                <td class="{{ $tdNum }}">
                                    @if ($row->incomplete)
                                        <span class="{{ $flag }}" title="{{ __('Incomplete recipe: estimated (minimum) cost') }}">~</span>
                                    @endif
                                    {{ Money::format($row->estimated_cost) }}
                                </td>
                                <td class="{{ $tdNum }}">
                                    @if ($row->incomplete)
                                        <span class="{{ $flag }}" title="{{ __('Incomplete recipe: estimated (minimum) cost') }}">~</span>
                                    @endif
                                    {{ Money::format($row->margin) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 dark:border-white/20">
                        <tr>
                            <td class="{{ $tf }}">{{ __('Total') }}</td>
                            <td class="{{ $tfNum }}">{{ $products->sum('quantity') }}</td>
                            <td class="{{ $tfNum }}">{{ Money::format($products->sum('revenue')) }}</td>
                            <td class="{{ $tfNum }}">{{ Money::format($products->sum('estimated_cost')) }}</td>
                            <td class="{{ $tfNum }}">{{ Money::format($products->sum('margin')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($products->contains('incomplete', true))
                <p class="mt-4 flex items-start text-sm text-gray-500 dark:text-gray-400">
                    @php $legend = __('~ = estimated: the recipe is incomplete (unknown quantities), the real cost is higher.'); @endphp
                    {{-- The sentence starts with "~": show that sign as the same chip the table uses. --}}
                    <span class="{{ $flag }} mt-px shrink-0">~</span>
                    <span>{{ str_starts_with($legend, '~') ? ltrim(mb_substr($legend, 1)) : $legend }}</span>
                </p>
            @endif
        @endif
    </x-filament::section>

    {{-- Fixed expenses --}}
    <x-filament::section :heading="__('Fixed expenses this month')" collapsible>
        <div class="-mx-3 overflow-x-auto">
            <table class="{{ $table }}">
                <thead class="border-b border-gray-200 dark:border-white/10">
                    <tr>
                        <th class="{{ $th }}">{{ __('Expense') }}</th>
                        <th class="{{ $thNum }}">{{ __('Amount') }}</th>
                        <th class="{{ $thNum }}">{{ __('Period') }}</th>
                        <th class="{{ $thNum }}">{{ __('Per month') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($fixedLines as $line)
                        <tr>
                            <td class="{{ $td }}">{{ $line->name }}</td>
                            <td class="{{ $tdNum }}">{{ Money::format($line->amount) }}</td>
                            <td class="{{ $tdNum }}">{{ trans_choice(':count month|:count months', $line->period_months, ['count' => $line->period_months]) }}</td>
                            <td class="{{ $tdNum }}">{{ Money::format($line->monthly) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-300 dark:border-white/20">
                    <tr>
                        <td class="{{ $tf }}" colspan="3">{{ __('Total') }}</td>
                        <td class="{{ $tfNum }}">{{ Money::format($fixed) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    {{-- Stock movements --}}
    <x-filament::section :heading="__('Stock movements')" collapsible>
        @if ($stock->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No stock movements this month.') }}</p>
        @else
            <div class="-mx-3 overflow-x-auto">
                <table class="{{ $table }}">
                    <thead class="border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th class="{{ $th }}">{{ __('Ingredient') }}</th>
                            <th class="{{ $thNum }}">{{ __('In') }}</th>
                            <th class="{{ $thNum }}">{{ __('Out') }}</th>
                            <th class="{{ $thNum }}">{{ __('Net') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($stock as $row)
                            @php $unit = \App\Models\Ingredient::labelForUnit($row->unit); @endphp
                            <tr>
                                <td class="{{ $td }}">{{ $row->name }}</td>
                                <td class="{{ $tdNum }}">{{ $qty($row->in) }} <span class="text-gray-500 dark:text-gray-400">{{ $unit }}</span></td>
                                <td class="{{ $tdNum }}">{{ $qty($row->out) }} <span class="text-gray-500 dark:text-gray-400">{{ $unit }}</span></td>
                                <td class="px-3 py-2.5 text-end font-medium whitespace-nowrap tabular-nums text-gray-950 dark:text-white">{{ $qty($row->net) }} <span class="font-normal text-gray-500 dark:text-gray-400">{{ $unit }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
